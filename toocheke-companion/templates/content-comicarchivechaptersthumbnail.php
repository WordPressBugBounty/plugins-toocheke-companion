<?php
/**
 * Template part for thumbnail-list archive of comics, grouped by chapter
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
//for each chapter, show all posts
$chapter_args = array(
    'taxonomy' => 'chapters',
    'style' => 'none',
    'orderby' => 'meta_value_num',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => 'chapter-order',
            'type' => 'NUMERIC',
        )),
    'show_count' => 0,
);
$chapters = get_categories($chapter_args);

foreach ($chapters as $chapter) {

    $chapter_comics_args = array(
        'post_type' => 'comic',
        'order' => $comic_order,
        'nopaging' => true,
        "tax_query" => array(
            array(
                'taxonomy' => "chapters", // use the $tax you define at the top of your script
                'field' => 'term_id',
                'terms' => $chapter->term_id, // use the current term in your foreach loop
            ),
        ),
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );
    $chapter_comics_query = new WP_Query($chapter_comics_args);

    if ($chapter_comics_query->have_posts()) {
        echo '<h3>' . wp_kses_data($chapter->name) . '</h3>';
        ?>
<ul id="comic-list">
      <?php

/* Start the Loop */
while ($chapter_comics_query->have_posts()): $chapter_comics_query->the_post();

    /*
     * Include the Post-Type-specific template for the content.
     * If you want to override this in a child theme, then include a file
     * called content-___.php (where ___ is the Post Type name) and that will be used instead.
     */
    $templates->get_template_part('content', 'comiclistitem');

endwhile;
?>
</ul>
        <?php
        wp_reset_postdata();
        ?>
	  <p>&nbsp;</p>
	  <?php
    } // if ($chapter_comics_query->have_posts()
} // foreach($chapters
?>

<?php

else:

    $templates->get_template_part('content', 'none');

endif;
?>
