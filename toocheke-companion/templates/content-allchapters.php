<?php
/**
 * Template part for displaying all chapters
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */

/**
 * Get latest six chapters of comics. If there are no chapters or chapters with no comics, don't display.
 */
$total_active_chapters = 0;
$series_id = get_query_var('series_id');
$chapter_comic_order = get_option('toocheke-chapter-first-comic') ? get_option('toocheke-chapter-first-comic') : 'DESC';
$comic_order = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
//Get total number of chapters
$comic_ids = toocheke_get_chapter_comic_ids($series_id);


$chapter_paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$chapters_per_page = 60;

$all_chapters = get_terms(array(
    'taxonomy'   => 'chapters',
    'object_ids' => $comic_ids,
    'hide_empty' => true,
    'orderby'    => 'order_clause',
    'order'      => 'ASC',
    'meta_query' => array(
        'relation'     => 'OR',
        'order_clause' => array(
            'key'  => 'chapter-order',
            'type' => 'NUMERIC',
        ),
        array(
            'key'     => 'chapter-order',
            'compare' => 'NOT EXISTS',
        ),
    ),
));

if (is_wp_error($all_chapters)) {
    $all_chapters = array();
}

$total_active_chapters = count($all_chapters);
$total_number_of_pages = ceil($total_active_chapters / $chapters_per_page);
$paged_offset = ($chapter_paged - 1) * $chapters_per_page;
$chapters = array_slice($all_chapters, $paged_offset, $chapters_per_page);

//display chapters with link to first comic
if ($chapters) {
    ?>
                <!-- START COMIC CHAPTER LIST-->
                <div id="all-chapters-wrapper" class="grid-container grid-four-cols">




                <?php

    foreach ($chapters as $chapter) {

    /**
     * Get latest/first post for this chapter
     */
    $args = array(
        'post_parent' => $series_id,
        'posts_per_page' => 1,
        'post_type' => 'comic',
        'orderby' => 'post_date',
        'order' => $comic_order,
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
            printf(wp_kses_data('%1$s'), '<img src="' . esc_url($term_img) . '" /><br/>');
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



                </div>
                <!--end chapters wrapper-->
                <div class="chapters-navigation">
                    <hr/>

<!-- Start Pagination -->
<?php
// Set up paginated links.
    $big = 999999999; // need an unlikely integer
    $links = paginate_links(array(
        'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
        'format' => '?paged=%#%',
        'current' => max(1, get_query_var('paged')),
        'total' => $total_number_of_pages,
        'prev_text' => wp_kses(__('<i class=\'fas fa-chevron-left\'></i>', 'toocheke-companion'), array('i' => array('class' => array()))),
        'next_text' => wp_kses(__('<i class=\'fas fa-chevron-right\'></i>', 'toocheke-companion'), array('i' => array('class' => array()))),
    ));

    if ($links):

    ?>

<div class="paginate-links">

         <?php echo wp_kses($links, array(
        'a' => array(
            'href' => array(),
            'class' => array(),
        ),
        'i' => array(
            'class' => array(),
        ),
        'span' => array(
            'class' => array(),
        ),
    )); ?>

     </div><!--/ .navigation -->
 <?php
endif;
    ?>
<!-- End Pagination -->
                    </div>
                    <!--end chapters-navigation-->
                <!-- END COMIC CHAPTER LIST-->
                <?php
}