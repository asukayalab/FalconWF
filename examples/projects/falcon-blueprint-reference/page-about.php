<?php
/* Template Name: Blueprint About */
if (!defined('ABSPATH')) { exit; }
get_header();
while(have_posts()) { the_post();
    $hero=FalconTheme\field(get_the_ID(),'example_hero',get_the_title());
    echo '<main id="main"><h1>'.esc_html($hero?:get_the_title()).'</h1>';the_content();
    $contact=FalconTheme\projectPageUrl('@@PROJECT_ID@@','contact');
    if($contact){echo '<p><a href="'.esc_url($contact).'">Hubungi kami</a></p>';}
    echo '</main>';
}
get_footer();
