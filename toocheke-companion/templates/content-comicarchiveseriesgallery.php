<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Template part for text list archive of comics
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */
$comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
$templates = new Toocheke_Companion_Template_Loader;
?>
<?php if (have_posts()): ?>

      <?php
//for each chapter, show all posts
$series_args = array(
    'post_type' => 'series',
    'nopaging' => true,
    'orderby' => 'post_date',
    'order' => 'ASC',
    'post_status' => 'publish',
);
$series_query = new WP_Query($series_args);
if ($series_query->have_posts()):

    while ($series_query->have_posts()): $series_query->the_post();
        $series_id = get_the_ID();
        $series_name = get_the_title();
        $args = array(
            'post_type' => 'comic',
            'post_status' => toocheke_companion_archive_post_status(),
            'order' => $comic_order,
            'nopaging' => true,
            'post_parent' => $series_id,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        $series_comics = get_posts($args);
        if ($series_comics) {
            echo '<h3>' . wp_kses_data($series_name) . '</h3> ';
            echo '<div id="comic-grid">';
            foreach ($series_comics as $comic) {
                setup_postdata($comic);
                $comic_url = get_permalink($comic->ID);
                if ($series_id && $series_id > 0) {
                    $comic_url = add_query_arg('sid', $series_id, $comic_url);
                }
                echo '<span class="comic-thumbnail-wrapper">';
                if (get_the_post_thumbnail($comic->ID) != '') {

                    toocheke_companion_comic_link_open($comic_url, $comic);
                    echo get_the_post_thumbnail($comic->ID, 'thumbnail');
                    toocheke_companion_comic_link_close($comic);

                } else {

                    toocheke_companion_comic_link_open($comic_url, $comic);
                    echo '<img src="';
                    echo esc_attr(toocheke_universal_catch_that_image_alt($comic));
                    echo '" alt="" />';
                    toocheke_companion_comic_link_close($comic);

                }
                echo '<br/>';
                echo '<span class="posted-on">Posted on ';
 toocheke_companion_comic_link_open($comic_url, $comic);
 echo wp_kses_data(mysql2date('F j, Y', $comic->post_date));
 toocheke_companion_comic_link_close($comic);
 echo '</span>';
                echo '</span>';
            } // foreach($series_comics
            echo '</div>';
            ?>
			  <p>&nbsp;</p>
			  <?php
        } // if ($series_comics
    endwhile;
    $series_query = null;
    wp_reset_postdata();
endif;
?>

<?php

else:

    $templates->get_template_part('content', 'none');

endif;
?>