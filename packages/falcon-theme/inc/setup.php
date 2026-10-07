<?php
namespace FalconTheme;
add_action('after_setup_theme',static function () {
    add_theme_support('title-tag'); add_theme_support('post-thumbnails');
    add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
});
