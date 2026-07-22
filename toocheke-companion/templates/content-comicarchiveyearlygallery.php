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
         echo '<div id="comic-grid">';
while (have_posts()): the_post();
echo '<span class="comic-thumbnail-wrapper">';
if (get_the_post_thumbnail(get_the_ID()) != '') {

    echo '<a href="';
    echo esc_url(get_permalink(get_the_ID()));
    echo '">';
    echo get_the_post_thumbnail(get_the_ID(), 'thumbnail');
    echo '</a>';

} else {

    echo '<a href="';
    echo esc_url(get_permalink(get_the_ID()));
    echo '" >';
    echo '<img src="';
    echo esc_attr(toocheke_universal_catch_that_image_alt(get_post()));
    echo '" alt="" />';
    echo '</a>';

}
echo '<br/>';
echo '<span class="posted-on">Posted on <a href="' . esc_url(get_permalink(get_the_ID())) . '">' . wp_kses_data(date('F j, Y', strtotime(get_the_date()))) . '</a></span>';
echo '</span>';
endwhile;
echo '</div>';
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