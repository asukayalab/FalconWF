<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" tabindex="-1" class="content-shell">
<?php if (is_archive()): ?><h1><?php the_archive_title(); ?></h1><?php endif; ?>
<?php if (have_posts()): while (have_posts()): the_post(); ?>
<article <?php post_class(); ?>>
<?php if (is_singular()): ?><h1><?php the_title(); ?></h1><?php \FalconTheme\contentDetails(get_the_ID()); ?><div class="entry-content"><?php the_content(); ?></div><?php wp_link_pages(); ?>
<?php else: ?><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?><?php endif; ?>
</article>
<?php endwhile; the_posts_pagination(); else: ?><h1>Belum ada konten</h1><p>Konten akan tersedia di sini.</p><?php endif; ?>
</main><?php get_footer(); ?>
