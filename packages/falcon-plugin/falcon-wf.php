<?php
/**
 * Plugin Name: Falcon WF
 * Description: Fondasi WordPress modular Asukayalab: konten, installer, izin dan konektor AI.
 * Version: 0.1.0-alpha.2
 * Requires at least: 6.7
 * Requires PHP: 8.3
 * Author: Asukayalab
 * Text Domain: falcon-wf
 */
if (!defined('ABSPATH')) { exit; }
if (version_compare(PHP_VERSION, '8.3', '<') || version_compare($GLOBALS['wp_version'], '6.7', '<') || is_multisite()) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>Falcon WF memerlukan PHP 8.3+, WordPress 6.7+ dan single-site.</p></div>';
    });
    return;
}
require_once __DIR__ . '/autoload.php';
\FalconWF\AI\InboundAuth::register();
register_activation_hook(__FILE__, [\FalconWF\Lifecycle::class, 'activate']);
register_deactivation_hook(__FILE__, [\FalconWF\Lifecycle::class, 'deactivate']);
add_action('plugins_loaded', static function () { \FalconWF\Bootstrap::boot(__FILE__); });
