<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" tabindex="-1" class="reference-shell reference-home" data-falcon-template="front-page">
<section class="reference-hero">
<p class="reference-label">CONTOH PAKET PROYEK · FALCON WF</p>
<?php if (is_page() && have_posts()): the_post(); ?>
<h1><?php echo esc_html(get_the_title()); ?></h1>
<div class="entry-content"><?php the_content(); wp_link_pages(); ?></div>
<?php else: ?>
<h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
<p class="reference-intro"><?php echo esc_html(get_bloginfo('description')); ?></p>
<?php endif; ?>
</section>
<?php if (shortcode_exists('falcon_listing') && post_type_exists('fwf_project')): ?>
<section class="reference-section" aria-labelledby="reference-projects">
<div class="reference-section-heading"><h2 id="reference-projects">Project terbaru</h2><?php $archive=get_post_type_archive_link('fwf_project'); if ($archive): ?><a href="<?php echo esc_url($archive); ?>">Lihat semua Project</a><?php endif; ?></div>
<?php echo do_shortcode('[falcon_listing type="fwf_project" limit="6"]'); ?>
</section>
<?php endif; ?>
</main><?php get_footer(); ?>
