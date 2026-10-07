<?php
namespace FalconWF;
final class Health {
    public static function report(): array {
        return ['wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'single_site' => !is_multisite(),
            'theme' => wp_get_theme()->get_stylesheet(), 'parent_installed' => wp_get_theme('falcon-theme')->exists(),
            'writer' => defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS ? 'immutable' : 'wordpress',
            'zip' => class_exists('ZipArchive'), 'schema' => (int)get_option('fwf_schema_version', 0)];
    }
}
