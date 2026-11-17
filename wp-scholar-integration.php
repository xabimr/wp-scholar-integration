<?php
/**
 * Plugin Name: WP Scholar Integration
 * Plugin URI: https://github.com/xabimr/wp-scholar-integration
 * Description: Integrates Google Scholar publications into WordPress using shortcodes.
 * Version: 1.5.4
 * Author: Xabier Rolan
 * Author URI: https://rolan.gal
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-scholar-integration
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WP_SCHOLAR_VERSION', '1.5.4');
define('WP_SCHOLAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SCHOLAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_SCHOLAR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once WP_SCHOLAR_PLUGIN_DIR . 'includes/class-wp-scholar-settings.php';
require_once WP_SCHOLAR_PLUGIN_DIR . 'includes/class-wp-scholar-scraper.php';
require_once WP_SCHOLAR_PLUGIN_DIR . 'includes/class-wp-scholar-shortcode.php';

/**
 * Main plugin class
 */
class WP_Scholar_Integration {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Settings object
     */
    public $settings;
    
    /**
     * Scraper object
     */
    public $scraper;
    
    /**
     * Shortcode object
     */
    public $shortcode;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize plugin components
        $this->settings = new WP_Scholar_Settings();
        $this->scraper = new WP_Scholar_Scraper();
        $this->shortcode = new WP_Scholar_Shortcode($this->scraper);
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add action to load plugin text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Add default options
        $default_options = array(
            'scholar_id' => '',
            'cache_time' => 24, // Cache time in hours
        );
        
        add_option('wp_scholar_options', $default_options);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('wp_scholar_cache_event');
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-scholar-integration',
            false,
            dirname(WP_SCHOLAR_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

// Initialize the plugin
function wp_scholar_integration_init() {
    return WP_Scholar_Integration::get_instance();
}

// Global for backwards compatibility
$GLOBALS['wp_scholar_integration'] = wp_scholar_integration_init();
