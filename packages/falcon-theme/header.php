<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main">Lewati ke konten</a>
<header class="site-header"><a class="wordmark" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(\FalconTheme\siteName()); ?></a><?php if (get_bloginfo('description')): ?><span class="site-tagline"><?php echo esc_html(get_bloginfo('description')); ?></span><?php endif; ?></header>
