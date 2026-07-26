<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Template part for displaying a grid of manga chapters, optionally
 * filtered by manga series and/or manga volume.
 *
 * Used by the [toocheke-all-manga-chapters] shortcode.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */

$templates       = new Toocheke_Companion_Template_Loader;
$section_title   = get_query_var('title');
$manga_series_id = get_query_var('manga_series_id');
$manga_volume_id = get_query_var('manga_volume_id');

$args = [
    'post_type'      => 'manga_chapter',
    'posts_per_page' => -1,
    'meta_key'       => 'chapter_number',
    'orderby'        => 'meta_value_num',
    'order'          => 'ASC',
];

$meta_query = [];

if (! empty($manga_series_id)) {
    $meta_query[] = [
        'key'     => 'series_id',
        'value'   => absint($manga_series_id),
        'compare' => '=',
        'type'    => 'NUMERIC',
    ];
}

if (! empty($manga_volume_id)) {
    $meta_query[] = [
        'key'     => 'volume_id',
        'value'   => absint($manga_volume_id),
        'compare' => '=',
        'type'    => 'NUMERIC',
    ];
}

if (! empty($meta_query)) {
    $args['meta_query'] = $meta_query;
}

$query = new WP_Query($args);
?>

<?php if (! empty($section_title)): ?>
    <h2 class="manga-grid-shortcode-header"><?php echo esc_html($section_title); ?></h2>
    <hr class="toocheke-hr manga-hr" />
<?php endif; ?>

<?php if ($query->have_posts()): ?>
    <div class="manga-row">
        <div class="manga-related-list-container">
            <?php while ($query->have_posts()): $query->the_post();
                        $templates->get_template_part('content', 'relatedmangachapter');
            endwhile; ?>
        </div>
    </div>
    <?php wp_reset_postdata(); ?>
<?php else: ?>
    <p class="font-weight-bold"><?php esc_html_e('No manga chapters found.', 'toocheke-companion'); ?></p>
<?php endif; ?>
