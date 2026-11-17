<?php
/**
 * WP Scholar Scraper Class
 *
 * @package WP_Scholar_Integration
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle scraping Google Scholar data
 */
class WP_Scholar_Scraper {

    /**
     * Base URL for Google Scholar
     */
    private $base_url = 'https://scholar.google.com/citations?';

    /** Publicaciones por página pedida a Scholar (máximo admitido: 100). */
    const PAGE_SIZE = 100;

    /** Máximo de páginas a recorrer por perfil (evita bucles y abusos). */
    const MAX_PAGES = 5;

    /** Segundos que se recuerda un fallo antes de volver a intentarlo. */
    const FAIL_TTL = 900;

    /** Máximo de IDs recordados para el refresco programado. */
    const MAX_KNOWN_IDS = 20;

    /**
     * Constructor
     */
    public function __construct() {
        // Add action for scheduled cache refresh
        add_action('wp_scholar_cache_event', array($this, 'refresh_cache'));
    }

    /**
     * Normaliza y valida un ID de Scholar (acepta también la URL del perfil).
     *
     * @param string $value ID o URL
     * @return string ID limpio, o cadena vacía si no es válido
     */
    public function normalize_id($value) {
        $value = trim((string) $value);
        if (preg_match('/[?&]user=([^&#]+)/', $value, $m)) {
            $value = urldecode($m[1]);
        }
        $value = sanitize_text_field($value);
        return preg_match('/^[A-Za-z0-9_-]{4,40}$/', $value) ? $value : '';
    }

    /**
     * Get publications for a scholar ID
     *
     * @param string $scholar_id The Google Scholar ID
     * @param bool $force_refresh Whether to force a refresh of the cache
     * @return array|WP_Error Array of publications or WP_Error on failure
     */
    public function get_publications($scholar_id = '', $force_refresh = false) {
        if (empty($scholar_id)) {
            // Try to get from settings
            $options = get_option('wp_scholar_options', array('scholar_id' => ''));
            $scholar_id = isset($options['scholar_id']) ? $options['scholar_id'] : '';

            if (empty($scholar_id)) {
                return new WP_Error('missing_id', __('No Google Scholar ID provided', 'wp-scholar-integration'));
            }
        }

        $scholar_id = $this->normalize_id($scholar_id);
        if ('' === $scholar_id) {
            return new WP_Error('invalid_id', __('Invalid Google Scholar ID', 'wp-scholar-integration'));
        }

        $cache_key  = 'wp_scholar_data_' . $scholar_id;
        $fail_key   = 'wp_scholar_fail_' . $scholar_id;
        $backup_key = 'wp_scholar_backup_' . $scholar_id;

        if (!$force_refresh) {
            // 1. Caché vigente
            $cached_data = get_transient($cache_key);
            if (false !== $cached_data) {
                return $cached_data;
            }

            // 2. Fallo reciente: no volver a pedir a Google; usar la última copia buena
            if (false !== get_transient($fail_key)) {
                $backup = get_option($backup_key, false);
                if (is_array($backup)) {
                    return $backup;
                }
                return new WP_Error('recent_failure', __('Google Scholar data is temporarily unavailable.', 'wp-scholar-integration'));
            }
        }

        // Fetch and parse the data
        $publications = $this->fetch_and_parse($scholar_id);

        if (is_wp_error($publications)) {
            // Caché negativa corta + servir la última copia buena si existe
            set_transient($fail_key, 1, self::FAIL_TTL);
            $backup = get_option($backup_key, false);
            if (is_array($backup)) {
                return $backup;
            }
            return $publications;
        }

        // Éxito: caché normal + copia de respaldo sin caducidad
        $cache_time = $this->get_cache_time();
        set_transient($cache_key, $publications, $cache_time * HOUR_IN_SECONDS);
        update_option($backup_key, $publications, false);
        delete_transient($fail_key);
        $this->remember_id($scholar_id);

        // Schedule refresh if not already scheduled
        if (!wp_next_scheduled('wp_scholar_cache_event')) {
            wp_schedule_event(time() + $cache_time * HOUR_IN_SECONDS, 'daily', 'wp_scholar_cache_event');
        }

        return $publications;
    }

    /**
     * Fetch and parse Google Scholar data (con paginación)
     *
     * @param string $scholar_id The Google Scholar ID
     * @return array|WP_Error Array of publications or WP_Error on failure
     */
    private function fetch_and_parse($scholar_id) {
        $author = '';
        $all    = array();

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $url = $this->base_url . 'user=' . rawurlencode($scholar_id)
                . '&hl=en&view_op=list_works&sortby=pubdate'
                . '&cstart=' . ($page * self::PAGE_SIZE)
                . '&pagesize=' . self::PAGE_SIZE;

            $response = wp_remote_get($url, array(
                'timeout'    => 15,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ));

            if (is_wp_error($response)) {
                // Si ya tenemos páginas previas, nos quedamos con lo obtenido
                return $page > 0 ? $this->build_result($author, $all) : $response;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if (200 !== $code) {
                if ($page > 0) {
                    break;
                }
                return new WP_Error(
                    'http_error',
                    sprintf(__('Google Scholar responded with HTTP %d (possibly blocked).', 'wp-scholar-integration'), $code)
                );
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                if ($page > 0) {
                    break;
                }
                return new WP_Error('empty_response', __('Empty response from Google Scholar', 'wp-scholar-integration'));
            }

            $parsed = $this->parse_html($body);
            if (is_wp_error($parsed)) {
                if ($page > 0) {
                    break;
                }
                return $parsed;
            }

            if ('' === $author) {
                $author = $parsed['author'];
            }
            $all = array_merge($all, $parsed['publications']);

            // Última página: menos filas de las pedidas
            if (count($parsed['publications']) < self::PAGE_SIZE) {
                break;
            }
        }

        return $this->build_result($author, $all);
    }

    private function build_result($author, $publications) {
        return array(
            'author'       => $author,
            'publications' => $publications,
            'fetched'      => time(),
        );
    }

    /**
     * Expresión XPath: el nodo tiene la clase indicada (entre otras).
     */
    private function has_class($class) {
        return "contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')";
    }

    /**
     * Parse HTML from Google Scholar
     *
     * @param string $html The HTML to parse
     * @return array|WP_Error Array with author + publications, or WP_Error if
     *                        the page is not a Scholar profile (CAPTCHA, consent, etc.)
     */
    private function parse_html($html) {
        $publications = array();

        // Load HTML into DOMDocument (forzando UTF-8 para no romper acentos)
        $dom = new DOMDocument();

        // Suppress warnings from malformed HTML
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        // Create DOMXPath object
        $xpath = new DOMXPath($dom);

        // Get author name
        $author_name = '';
        $name_node = $xpath->query('//div[@id="gsc_prf_in"]');
        if ($name_node && $name_node->length > 0) {
            $author_name = trim($name_node->item(0)->textContent);
        }

        // Get publication nodes
        $pub_nodes = $xpath->query('//tr[' . $this->has_class('gsc_a_tr') . ']');

        // Si no hay ni perfil ni filas, no es un perfil válido (CAPTCHA, bloqueo, ID erróneo)
        if ('' === $author_name && (!$pub_nodes || 0 === $pub_nodes->length)) {
            return new WP_Error(
                'not_a_profile',
                __('Google Scholar did not return a valid profile (blocked, CAPTCHA or wrong ID).', 'wp-scholar-integration')
            );
        }

        foreach ($pub_nodes as $node) {
            $title_node   = $xpath->query('.//a[' . $this->has_class('gsc_a_at') . ']', $node);
            $gray_nodes   = $xpath->query('.//div[' . $this->has_class('gs_gray') . ']', $node);
            $year_node    = $xpath->query('.//span[' . $this->has_class('gsc_a_h') . ']', $node);
            $cited_node   = $xpath->query('.//a[' . $this->has_class('gsc_a_ac') . ']', $node);

            $title   = $title_node->length > 0 ? trim($title_node->item(0)->textContent) : '';
            $authors = $gray_nodes->length > 0 ? trim($gray_nodes->item(0)->textContent) : '';
            $venue   = $gray_nodes->length > 1 ? trim($gray_nodes->item(1)->textContent) : '';
            $year    = $year_node->length > 0 ? trim($year_node->item(0)->textContent) : '';
            $cited   = $cited_node->length > 0 ? trim($cited_node->item(0)->textContent) : '0';

            // Get link to the publication
            $link = '';
            if ($title_node->length > 0) {
                $link = $title_node->item(0)->getAttribute('href');
                if (!empty($link) && strpos($link, 'http') !== 0) {
                    $link = 'https://scholar.google.com' . $link;
                }
            }

            // Get citation link
            $citation_link = '';
            if ($cited_node->length > 0) {
                $citation_link = $cited_node->item(0)->getAttribute('href');
                if (!empty($citation_link) && strpos($citation_link, 'http') !== 0) {
                    $citation_link = 'https://scholar.google.com' . $citation_link;
                }
            }

            $publications[] = array(
                'title' => $title,
                'authors' => $authors,
                'venue' => $venue,
                'year' => $year,
                'cited' => $cited,
                'link' => $link,
                'citation_link' => $citation_link
            );
        }

        return array(
            'author' => $author_name,
            'publications' => $publications
        );
    }

    /**
     * Recuerda los IDs usados (ajustes + shortcodes) para el refresco programado.
     */
    private function remember_id($scholar_id) {
        $ids = get_option('wp_scholar_known_ids', array());
        if (!is_array($ids)) {
            $ids = array();
        }
        if (!in_array($scholar_id, $ids, true)) {
            $ids[] = $scholar_id;
            $ids = array_slice($ids, -self::MAX_KNOWN_IDS);
            update_option('wp_scholar_known_ids', $ids, false);
        }
    }

    /**
     * Refresh the cache for all known scholar IDs (ajustes + shortcodes)
     */
    public function refresh_cache() {
        $options = get_option('wp_scholar_options', array('scholar_id' => ''));
        $ids = get_option('wp_scholar_known_ids', array());
        $ids = is_array($ids) ? $ids : array();

        if (!empty($options['scholar_id'])) {
            $ids[] = $options['scholar_id'];
        }

        foreach (array_unique($ids) as $id) {
            $this->get_publications($id, true);
            sleep(2); // no bombardear a Google
        }
    }

    /**
     * Get cache time from settings
     */
    private function get_cache_time() {
        $options = get_option('wp_scholar_options', array('cache_time' => 24));
        $hours = isset($options['cache_time']) ? intval($options['cache_time']) : 24;
        return $hours > 0 ? $hours : 24;
    }
}
