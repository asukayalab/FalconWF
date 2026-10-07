<?php
namespace FalconTheme;
add_action('wp_enqueue_scripts',static function () {
    $manifest=json_decode((string)@file_get_contents(get_template_directory().'/assets/manifest.json'),true);
    if (isset($manifest['main.css']) && is_file(get_template_directory().'/assets/'.$manifest['main.css'])) {
        wp_enqueue_style('falcon-theme',get_template_directory_uri().'/assets/'.$manifest['main.css'],[],null);
    }
});
