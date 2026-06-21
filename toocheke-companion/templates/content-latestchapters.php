<?php
/**
 * Template part for displaying latest chapters
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */

/**
 * Get latest six chapters of comics. If there are no chapters or chapters with no comics, don't display.
 */
$series_id = get_query_var('series_id');
$chapter_comic_order = get_option('toocheke-chapter-first-comic') ? get_option('toocheke-chapter-first-comic') : 'DESC';
$comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
$chapter_args = array(
    'taxonomy' => 'chapters',
    'style' => 'none',
    'orderby' => 'meta_value_num',
    'order' => $comic_order,
    'meta_query' => array(
        array(
            'key' => 'chapter-order',
            'type' => 'NUMERIC',
        )),
    'show_count' => 0,
    'number' => 6,
);
$chapters_list = get_categories($chapter_args);

if ($chapters_list) {
    ?>
                <!-- START COMIC CHAPTER LIST-->
                <div id="chapter-wrapper" class="grid-container grid-three-cols">
              

                     
             
                        <?php

    foreach ($chapters_list as $chapter) {
    /**
     * Get latest/first post for this chapter
     */
    $args = array(
        'post_parent' => $series_id,
        'posts_per_page' => 1,
        'post_type' => 'comic',
        'order' => $chapter_comic_order,
        "tax_query" => array(
            array(
                'taxonomy' => "chapters",
                'field' => 'term_id',
                'terms' => $chapter->term_id,
            ),
        ),
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );
    $first_comic_query = new WP_Query($args);

    if ($first_comic_query->have_posts()) {
        $first_comic_query->the_post();
        $first_comic_link = get_post_permalink();
        if ($series_id) {
            $first_comic_link = add_query_arg('sid', $series_id, $first_comic_link);
        }
        wp_reset_postdata();

        // Decide where this chapter thumbnail should link to
        $link_to_archive = get_option('toocheke-chapter-archive-link') && 1 == get_option('toocheke-chapter-archive-link');

        if ($link_to_archive) {
            $archive_link = get_term_link($chapter);
            if (!is_wp_error($archive_link)) {
                $link_url = $series_id ? add_query_arg('sid', $series_id, $archive_link) : $archive_link;
            } else {
                $link_url = $first_comic_link;
            }
        } else {
            $link_url = $first_comic_link;
        }

        printf(wp_kses_data('%1$s'), '<div class="chapter-thumbnail">');
        printf(wp_kses_data('%1$s'), '<a href="' . esc_url($link_url) . '">');
        $term_id = absint($chapter->term_id);
        $thumb_id = get_term_meta($term_id, 'chapter-image-id', true);

        if (!empty($thumb_id)) {
            $term_img = wp_get_attachment_url($thumb_id);
            printf(wp_kses_data('%1$s'), '<img src="' . esc_attr($term_img) . '" /><br/>');
        } else {
            ?>
            <img src="<?php echo esc_attr(plugins_url('toocheke-companion' . '/img/default-thumbnail-image.png')); ?>" />
            <?php
        }

        echo wp_kses_data($chapter->name);
        echo '</a></div>';
    }
}
// Reset Post Data
wp_reset_postdata();

    ?>
            
                    <!--end chapters row-->
            
                 
                </div>

                <?php
}