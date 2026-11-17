<?php
/**
 * Limpieza al desinstalar el plugin.
 *
 * @package WP_Scholar_Integration
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

delete_option('wp_scholar_options');
delete_option('wp_scholar_known_ids');

// Transients, copias de respaldo y marcas de fallo
$like_patterns = array(
    $wpdb->esc_like('_transient_wp_scholar_') . '%',
    $wpdb->esc_like('_transient_timeout_wp_scholar_') . '%',
    $wpdb->esc_like('wp_scholar_backup_') . '%',
);
foreach ($like_patterns as $pattern) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
}

wp_clear_scheduled_hook('wp_scholar_cache_event');
