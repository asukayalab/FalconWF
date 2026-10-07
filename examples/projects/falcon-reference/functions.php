<?php
namespace FalconReference;
if (!defined('ABSPATH')) { exit; }
add_action('wp_enqueue_scripts',static function () {
    wp_enqueue_style('falcon-reference',get_stylesheet_directory_uri().'/assets/design.css',['falcon-theme'],wp_get_theme()->get('Version'));
});
add_action('pre_get_posts',static function ($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_post_type_archive('fwf_project')) {
        $query->set('post_status','publish');
        $query->set('has_password',false);
    }
});
