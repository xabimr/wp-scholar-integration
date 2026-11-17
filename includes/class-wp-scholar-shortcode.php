<?php
/**
 * WP Scholar Shortcode Class
 *
 * @package WP_Scholar_Integration
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle shortcode functionality
 */
class WP_Scholar_Shortcode {

    /**
     * Scraper instance
     */
    private $scraper;

    /**
     * Constructor
     *
     * @param WP_Scholar_Scraper $scraper Scraper instance
     */
    public function __construct($scraper) {
        $this->scraper = $scraper;
        
        // Register shortcode
        add_shortcode('google_scholar', array($this, 'render_shortcode'));
        
        // Registrar estilos; se encolan al renderizar el shortcode (funciona con Elementor, widgets, etc.)
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }

    /**
     * Register frontend assets
     */
    public function register_assets() {
        wp_register_style(
            'wp-scholar-styles',
            WP_SCHOLAR_PLUGIN_URL . 'assets/css/wp-scholar-public.css',
            array(),
            WP_SCHOLAR_VERSION
        );
        wp_register_script(
            'wp-scholar-sort',
            WP_SCHOLAR_PLUGIN_URL . 'assets/js/wp-scholar-sort.js',
            array(),
            WP_SCHOLAR_VERSION,
            true
        );
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => '',
                'limit' => 10,
                'year' => '',
                'show_author' => 'yes',
                'show_authors' => 'yes',
                'show_venue' => 'yes',
                'show_year' => 'yes',
                'show_citations' => 'yes',
                'title_tag' => 'h3',
                'sort' => 'year_desc',     // year_desc | year_asc | cited_desc
                'show_sort' => 'yes',      // selector de orden en la web
            ),
            $atts,
            'google_scholar'
        );
        
        // Get scholar ID from attribute or settings
        $scholar_id = !empty($atts['id']) ? $this->scraper->normalize_id($atts['id']) : '';

        // Lista blanca de etiquetas para el título (evita inyección de atributos)
        $allowed_tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span');
        $title_tag = strtolower(trim($atts['title_tag']));
        if (!in_array($title_tag, $allowed_tags, true)) {
            $title_tag = 'h3';
        }

        wp_enqueue_style('wp-scholar-styles');
        
        // Get publications
        $data = $this->scraper->get_publications($scholar_id);
        
        if (is_wp_error($data)) {
            if (current_user_can('manage_options')) {
                return '<div class="wp-scholar-error">' . esc_html($data->get_error_message()) . '</div>';
            }
            // Visitantes: no mostrar errores técnicos
            return '';
        }
        
        // Start output buffer
        ob_start();
        
        // Output container
        echo '<div class="wp-scholar-container">';
        
        // Author information if available and requested
        if (!empty($data['author']) && $atts['show_author'] === 'yes') {
            echo '<div class="wp-scholar-author">';
            echo '<h2>' . esc_html__('Publications by', 'wp-scholar-integration') . ' ' . esc_html($data['author']) . '</h2>';
            echo '</div>';
        }
        
        // Filter by year if specified
        $publications = $data['publications'];
        if (!empty($atts['year'])) {
            $filtered_pubs = array();
            foreach ($publications as $pub) {
                if ($pub['year'] == $atts['year']) {
                    $filtered_pubs[] = $pub;
                }
            }
            $publications = $filtered_pubs;
        }
        
        // Orden inicial (antes del límite: limit="10" sort="cited_desc" = las 10 más citadas)
        $sort = in_array($atts['sort'], array('year_desc', 'year_asc', 'cited_desc'), true) ? $atts['sort'] : 'year_desc';
        $publications = $this->sort_publications($publications, $sort);

        // Limit the number of publications
        $limit = intval($atts['limit']);
        if ($limit > 0 && count($publications) > $limit) {
            $publications = array_slice($publications, 0, $limit);
        }
        
        // Output publications
        if (!empty($publications)) {
            if ($atts['show_sort'] === 'yes' && count($publications) > 1) {
                wp_enqueue_script('wp-scholar-sort');
                echo '<div class="wp-scholar-sort">';
                echo '<label>' . esc_html__('Sort by', 'wp-scholar-integration') . ' ';
                echo '<select class="wp-scholar-sort-select">';
                $labels = array(
                    'year_desc'  => __('Year (newest first)', 'wp-scholar-integration'),
                    'year_asc'   => __('Year (oldest first)', 'wp-scholar-integration'),
                    'cited_desc' => __('Citations (most cited first)', 'wp-scholar-integration'),
                );
                foreach ($labels as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '"' . selected($sort, $value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select></label>';
                echo '</div>';
            }

            echo '<ul class="wp-scholar-publications">';
            
            foreach ($publications as $pub) {
                echo '<li class="wp-scholar-publication" data-year="' . esc_attr((int) $pub['year']) . '" data-cited="' . esc_attr((int) $pub['cited']) . '">';
                
                // Title with link
                echo '<' . $title_tag . ' class="wp-scholar-title">';
                if (!empty($pub['link'])) {
                    echo '<a href="' . esc_url($pub['link']) . '" target="_blank" rel="noopener noreferrer">';
                    echo esc_html($pub['title']);
                    echo '</a>';
                } else {
                    echo esc_html($pub['title']);
                }
                echo '</' . $title_tag . '>';
                
                // Authors
                if (!empty($pub['authors']) && $atts['show_authors'] === 'yes') {
                    echo '<div class="wp-scholar-authors">' . esc_html($pub['authors']) . '</div>';
                }
                
                // Publication details
                echo '<div class="wp-scholar-details">';
                
                // Venue
                if (!empty($pub['venue']) && $atts['show_venue'] === 'yes') {
                    $venue = $pub['venue'];
                    // Scholar ya añade el año al final de la revista: evitar duplicarlo
                    if (!empty($pub['year']) && $atts['show_year'] === 'yes') {
                        $venue = preg_replace('/[,\s]*' . preg_quote($pub['year'], '/') . '\s*$/u', '', $venue);
                    }
                    echo '<span class="wp-scholar-venue">' . esc_html($venue) . '</span>';
                }
                
                // Year
                if (!empty($pub['year']) && $atts['show_year'] === 'yes') {
                    echo '<span class="wp-scholar-year">' . esc_html($pub['year']) . '</span>';
                }
                
                // Citations
                if (!empty($pub['cited']) && $atts['show_citations'] === 'yes' && $pub['cited'] !== '0') {
                    echo '<span class="wp-scholar-citations">';
                    if (!empty($pub['citation_link'])) {
                        echo '<a href="' . esc_url($pub['citation_link']) . '" target="_blank" rel="noopener noreferrer">';
                        echo esc_html__('Cited by', 'wp-scholar-integration') . ' ' . esc_html($pub['cited']);
                        echo '</a>';
                    } else {
                        echo esc_html__('Cited by', 'wp-scholar-integration') . ' ' . esc_html($pub['cited']);
                    }
                    echo '</span>';
                }
                
                echo '</div>'; // .wp-scholar-details
                
                echo '</li>';
            }
            
            echo '</ul>';
        } else {
            echo '<p class="wp-scholar-no-results">' . esc_html__('No publications found.', 'wp-scholar-integration') . '</p>';
        }
        
        echo '</div>'; // .wp-scholar-container
        
        // Return the output
        return ob_get_clean();
    }

    /**
     * Ordena publicaciones (estable: respeta el orden de Scholar en empates).
     */
    private function sort_publications($publications, $sort) {
        $indexed = array();
        foreach (array_values($publications) as $i => $pub) {
            $indexed[] = array($i, $pub);
        }
        usort($indexed, function ($a, $b) use ($sort) {
            $ya = (int) $a[1]['year'];
            $yb = (int) $b[1]['year'];
            $ca = (int) $a[1]['cited'];
            $cb = (int) $b[1]['cited'];
            switch ($sort) {
                case 'year_asc':
                    $r = ($ya ? $ya : 9999) <=> ($yb ? $yb : 9999);
                    $r = $r ? $r : $cb <=> $ca;
                    break;
                case 'cited_desc':
                    $r = $cb <=> $ca;
                    $r = $r ? $r : $yb <=> $ya;
                    break;
                default: // year_desc
                    $r = $yb <=> $ya;
                    $r = $r ? $r : $cb <=> $ca;
            }
            return $r ? $r : $a[0] <=> $b[0];
        });
        return array_map(function ($row) { return $row[1]; }, $indexed);
    }
}
