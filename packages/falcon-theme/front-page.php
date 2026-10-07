<?php
if (!defined('ABSPATH')) { exit; }
// Only the fallback coming-soon template opts out of indexing. Child templates
// retain WordPress' site visibility policy instead of inheriting this restriction.
add_filter('wp_robots',static function ($robots) { $robots['noindex']=true; $robots['nofollow']=true; return $robots; });
get_header(); get_template_part('template-parts/coming-soon'); get_footer();
