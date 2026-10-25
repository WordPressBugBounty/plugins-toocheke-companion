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
$templates = new Toocheke_Companion_Template_Loader;
$comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
?>
<?php if (have_posts()): ?>
      <?php
//for each collection, show all posts
$collection_args = array(
    'taxonomy' => 'collections',
    'style' => 'none',
    'orderby' => 'meta_value_num',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => 'collection-order',
            'type' => 'NUMERIC',
        )),
    'show_count' => 0,
);
$collections = get_categories($collection_args);

foreach ($collections as $collection) {

    $args = array(
        'post_type' => 'comic',
        'post_status' => toocheke_companion_archive_post_status(),
        'order' => $comic_order,
        'nopaging' => true,
        "tax_query" => array(
            array(
                'taxonomy' => "collections", // use the $tax you define at the top of your script
                'field' => 'term_id',
                'terms' => $collection->term_id, // use the current term in your foreach loop
            ),
        ),
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );
    $collections_posts = get_posts($args);
    if ($collections_posts) {
        echo '<h3>' . wp_kses_data($collection->name) . '</h3> ';
        echo '<div id="comic-grid">';
        foreach ($collections_posts as $comic) {
            setup_postdata($comic);
            $comic_url = get_permalink($comic->ID);
            if ($collection->term_id && $collection->term_id > 0) {
                $comic_url = add_query_arg('col', $collection->term_id, $comic_url);
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
        } // foreach($collections_posts
        echo '</div>';
        ?>
	  <p>&nbsp;</p>
	  <?php
    } // if ($collections_posts
} // foreach($collections
?>

<?php

else:

    $templates->get_template_part('content', 'none');

endif;
?>