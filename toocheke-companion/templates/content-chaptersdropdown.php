<?php
/**
 * Template part for chapters dropdown
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */

$display_chapters_dropdown = get_option('toocheke-chapter-dropdown') && 1 == get_option('toocheke-chapter-dropdown');
$comic_order = get_option('toocheke-chapter-first-comic') ? get_option('toocheke-chapter-first-comic') : 'DESC';
$series_id = null;
$series_id = isset($_GET['sid']) ? (int) $_GET['sid'] : null;
if (get_query_var('series_id')) {
    $series_id = (int) get_query_var('series_id');
}
if (is_singular('series') && !$series_id) {
    $series_id = get_the_ID();
}

// Same archive-link option used on the Chapters page template
$link_to_archive = get_option('toocheke-chapter-archive-link') && 1 == get_option('toocheke-chapter-archive-link');
?>
<?php
if ($display_chapters_dropdown):

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

    $chapters_list = get_categories($chapter_args);
    if ($chapters_list) {
        ?>
         <h3>CHOOSE YOUR STARTING POINT</h3>
          <div class="chapter-inline-dropdown">
	<select id="chapters-drodpown" onchange="document.location.href=this.options[this.selectedIndex].value" class="input-sm">
	<option value="">Select Chapter</option>
	<?php

        foreach ($chapters_list as $chapter) {

            // Always check series membership first, regardless of link mode
            $args = array(
                'posts_per_page' => 1,
                'post_parent' => $series_id,
                'post_type' => 'comic',
                'orderby' => 'post_date',
                'order' => 'ASC',
                'fields' => 'ids',
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
                'ignore_sticky_posts' => true,
            );
            $first_comic_query = new WP_Query($args);

            if (empty($first_comic_query->posts)) {
                // No comics in this chapter for this series - skip it entirely
                continue;
            }

            $first_comic_id = $first_comic_query->posts[0];

            // Now decide which URL to actually use
            $link_url = '';

            if ($link_to_archive) {
                $archive_link = get_term_link($chapter);
                if (!is_wp_error($archive_link)) {
                    $link_url = $series_id ? add_query_arg('sid', $series_id, $archive_link) : $archive_link;
                }
            }

            if (empty($link_url)) {
                $link_url = add_query_arg('sid', $series_id, get_permalink($first_comic_id));
            }

            printf(wp_kses_data('%1$s'), '<option value="' . esc_url($link_url) . '">');
            echo wp_kses_data($chapter->name);
            echo '</option>';
        }
        ?>
	</select>
    </div>
	<?php
    }
endif;

?>