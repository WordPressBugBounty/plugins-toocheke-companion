<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Template part for displaying a grid of manga volumes, optionally
 * filtered by manga series.
 *
 * Used by the [toocheke-all-manga-volumes] shortcode.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Toocheke
 */

$templates        = new Toocheke_Companion_Template_Loader;
$section_title    = get_query_var('title');
$manga_series_id  = get_query_var('manga_series_id');

$args = [
    'post_type'      => 'manga_volume',
    'posts_per_page' => -1,
    'meta_key'       => 'volume_number',
    'orderby'        => 'meta_value_num',
    'order'          => 'ASC',
];

if (! empty($manga_series_id)) {
    $args['meta_query'] = [
        [
            'key'     => 'series_id',
            'value'   => absint($manga_series_id),
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
    ];
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
                        $templates->get_template_part('content', 'relatedmangavolume');
            endwhile; ?>
        </div>
    </div>
    <?php wp_reset_postdata(); ?>
<?php else: ?>
    <p class="font-weight-bold"><?php esc_html_e('No manga volumes found.', 'toocheke-companion'); ?></p>
<?php endif; ?>
