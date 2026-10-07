<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" tabindex="-1" class="reference-shell" data-falcon-template="project-single">
<?php while (have_posts()): the_post(); ?>
<article <?php post_class(); ?>><p class="reference-label">PROJECT</p><h1><?php echo esc_html(get_the_title()); ?></h1>
<?php \FalconTheme\contentDetails(get_the_ID()); ?>
<div class="entry-content"><?php the_content(); wp_link_pages(); ?></div></article>
<?php endwhile; ?>
</main><?php get_footer(); ?>
