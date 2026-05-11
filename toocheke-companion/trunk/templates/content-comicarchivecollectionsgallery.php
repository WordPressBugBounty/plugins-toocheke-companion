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
?>
<?php if (have_posts()): ?>
     <header class="page-header">
            <?php
the_archive_title('<h1 class="page-title">', '</h1>');

?>
      </header><!-- .page-header -->
      <hr/>
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

                echo '<a href="';
                echo  esc_url($comic_url);
                echo '">';
                echo get_the_post_thumbnail($comic->ID, 'thumbnail');
                echo '</a>';

            } else {

                echo '<a href="';
                echo  esc_url($comic_url);
                echo '" >';
                echo '<img src="';
                echo esc_attr(toocheke_catch_that_image_alt($comic));
                echo '" alt="" />';
                echo '</a>';

            }
            echo '<br/>';
            echo '<span class="posted-on">Posted on <a href="' . esc_url($comic_url) . '">' . wp_kses_data(date('F j, Y', strtotime($comic->post_date))) .'</a></span>';
            echo '</span>';
        } // foreach($collections_posts
        echo '</div>';
    } // if ($collections_posts
    ?>
	  <p>&nbsp;</p>
	  <?php
} // foreach($collections
?>

<?php

else:

    $templates->get_template_part('content', 'none');

endif;
?>