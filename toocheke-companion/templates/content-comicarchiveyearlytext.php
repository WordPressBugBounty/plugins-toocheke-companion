<?php
/**
 * Template part for text list archive of comics
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
        <?php
while (have_posts()): the_post();
    ?>
			  <div class="comic-archive-item">
	<span class="comic-archive-date"><?php echo get_the_date(); ?></span>
	<span class="comic-archive-title"><a href="<?php the_permalink();?>" title="<?php echo esc_attr(get_the_title()) ?>"><?php echo wp_kses_data(get_the_title()) ?></a></span>
	</div>

	          <?php
endwhile;
?>
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