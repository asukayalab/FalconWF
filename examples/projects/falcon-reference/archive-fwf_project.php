<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" tabindex="-1" class="reference-shell" data-falcon-template="project-archive">
<p class="reference-label">PORTOFOLIO</p><h1><?php echo esc_html(post_type_archive_title('',false)); ?></h1>
<?php if (have_posts()): ?><div class="reference-grid">
<?php while (have_posts()): the_post(); ?>
<article <?php post_class('reference-card'); ?>><h2><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
<p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()),30)); ?></p></article>
<?php endwhile; ?></div><?php the_posts_pagination(); else: ?><p>Belum ada Project yang dipublikasikan.</p><?php endif; ?>
</main><?php get_footer(); ?>
