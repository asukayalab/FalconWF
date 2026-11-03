<?php
/* Template Name: Blueprint Contact */
if (!defined('ABSPATH')) { exit; }
get_header();
while(have_posts()) { the_post();echo '<main id="main"><h1>'.esc_html(get_the_title()).'</h1>';the_content();echo '</main>'; }
get_footer();
