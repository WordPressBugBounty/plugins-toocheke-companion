<?php
/**
 * Template part for thumbnail-list archive of comics, segmented by year
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */
$templates = new Toocheke_Companion_Template_Loader;
$comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
$comic_order_asc = ('ASC' === strtoupper($comic_order));
?>
<?php if (have_posts()): ?>

      <?php
$archive_args = array(
	'post_type' => 'comic',
	'type' => 'yearly',
);
?>
<ul id="archive-menu">
			<?php wp_get_archives($archive_args); ?>
		</ul>

<ul id="comic-list">
      <?php

/* Start the Loop */
while (have_posts()): the_post();

    /*
     * Include the Post-Type-specific template for the content.
     * If you want to override this in a child theme, then include a file
     * called content-___.php (where ___ is the Post Type name) and that will be used instead.
     */
    $templates->get_template_part('content', 'comiclistitem');

endwhile;
?>
</ul>

 <!-- Start Pagination -->
 <?php
the_posts_navigation(
    array(
        'prev_text' => $comic_order_asc ? __('Newer comics', 'toocheke-companion') : __('Older comics', 'toocheke-companion'),
        'next_text' => $comic_order_asc ? __('Older comics', 'toocheke-companion') : __('Newer comics', 'toocheke-companion'),
        'screen_reader_text' => __('Posts navigation', 'toocheke-companion'),
    )
);

?>
<?php

else:

    $templates->get_template_part('content', 'none');

endif;
?>
