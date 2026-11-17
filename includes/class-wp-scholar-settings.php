<?php
/**
 * WP Scholar Settings Class
 *
 * @package WP_Scholar_Integration
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class to handle plugin settings
 */
class WP_Scholar_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        // Add menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add options page to admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Google Scholar Integration', 'wp-scholar-integration'),
            __('Google Scholar', 'wp-scholar-integration'),
            'manage_options',
            'wp-scholar-integration',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wp_scholar_settings',
            'wp_scholar_options',
            array($this, 'sanitize_options')
        );

        add_settings_section(
            'wp_scholar_general_section',
            __('General Settings', 'wp-scholar-integration'),
            array($this, 'render_general_section'),
            'wp-scholar-integration'
        );

        add_settings_field(
            'scholar_id',
            __('Google Scholar ID or URL', 'wp-scholar-integration'),
            array($this, 'render_scholar_id_field'),
            'wp-scholar-integration',
            'wp_scholar_general_section'
        );

        add_settings_field(
            'cache_time',
            __('Cache Duration (hours)', 'wp-scholar-integration'),
            array($this, 'render_cache_time_field'),
            'wp-scholar-integration',
            'wp_scholar_general_section'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($options) {
        $sanitized_options = array();

        // Sanitize scholar ID or URL
        if (isset($options['scholar_id'])) {
            // Extract ID from URL if a URL is provided
            if (strpos($options['scholar_id'], 'user=') !== false) {
                preg_match('/user=([^&]+)/', $options['scholar_id'], $matches);
                if (isset($matches[1])) {
                    $sanitized_options['scholar_id'] = sanitize_text_field($matches[1]);
                } else {
                    $sanitized_options['scholar_id'] = sanitize_text_field($options['scholar_id']);
                }
            } else {
                $sanitized_options['scholar_id'] = sanitize_text_field($options['scholar_id']);
            }
        }

        // Sanitize cache time
        if (isset($options['cache_time'])) {
            $sanitized_options['cache_time'] = absint($options['cache_time']);
            if ($sanitized_options['cache_time'] < 1) {
                $sanitized_options['cache_time'] = 24; // Default to 24 hours
            }
        }

        return $sanitized_options;
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . __('Configure your Google Scholar integration settings below.', 'wp-scholar-integration') . '</p>';
        echo '<p>' . __('Use the shortcode [google_scholar] to display publications on any page or post.', 'wp-scholar-integration') . '</p>';
    }

    /**
     * Render scholar ID field
     */
    public function render_scholar_id_field() {
        $options = get_option('wp_scholar_options', array('scholar_id' => ''));
        $scholar_id = isset($options['scholar_id']) ? $options['scholar_id'] : '';

        echo '<input type="text" name="wp_scholar_options[scholar_id]" value="' . esc_attr($scholar_id) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your Google Scholar ID (e.g., "ABC123XYZ") or the full profile URL.', 'wp-scholar-integration') . '</p>';
    }

    /**
     * Render cache time field
     */
    public function render_cache_time_field() {
        $options = get_option('wp_scholar_options', array('cache_time' => 24));
        $cache_time = isset($options['cache_time']) ? $options['cache_time'] : 24;

        echo '<input type="number" name="wp_scholar_options[cache_time]" value="' . esc_attr($cache_time) . '" class="small-text" min="1" />';
        echo '<p class="description">' . __('Number of hours to cache the Google Scholar data. Minimum 1 hour.', 'wp-scholar-integration') . '</p>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wp_scholar_settings');
                do_settings_sections('wp-scholar-integration');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get scholar ID
     */
    public function get_scholar_id() {
        $options = get_option('wp_scholar_options', array('scholar_id' => ''));
        return isset($options['scholar_id']) ? $options['scholar_id'] : '';
    }

    /**
     * Get cache time
     */
    public function get_cache_time() {
        $options = get_option('wp_scholar_options', array('cache_time' => 24));
        return isset($options['cache_time']) ? $options['cache_time'] : 24;
    }
}
