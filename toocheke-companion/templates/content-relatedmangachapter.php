<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
    /**
     * Template part for displaying a single manga chapter within a grid
     * (used by the chapters list on a manga volume page, and by the
     * [toocheke-all-manga-chapters] shortcode).
     *
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Toocheke
     */
    $manga_chapter_id = get_the_ID();
$release_date    = get_post_meta($manga_chapter_id, 'release_date', true);
$pages           = get_post_meta($manga_chapter_id, 'pages', true);

$formatted_release_date = $release_date ? (new DateTime($release_date))->format('M. d, Y') : '';
?>

<div class="manga-related-item-container fade-in">
    <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
        <div class="manga-related-item-thumbnail manga-thumbnail">
            <?php if (has_post_thumbnail()) {
                the_post_thumbnail('full');
            } else { ?>
                <img src="<?php echo esc_url(plugins_url('toocheke-companion/img/no-image.png')); ?>" alt="<?php the_title_attribute(); ?>" />
            <?php } ?>
        </div>
    </a>

    <div class="manga-related-info-container">
        <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
            <h3 class="manga-related-info-title"><?php the_title(); ?></h3>
        </a>

        <div class="manga-data-pages">
            <span><?php echo esc_html($formatted_release_date); ?></span> |
            <span><?php /* translators: %s: number of pages */ echo sprintf(esc_html(_n('%s page', '%s pages', $pages, 'toocheke-companion')), esc_html($pages)); ?></span>
        </div>

        <a href="<?php echo esc_url(get_permalink()); ?>"
           title="<?php /* translators: %s: manga chapter title */ printf(esc_attr__('Read %s', 'toocheke-companion'), esc_attr(get_the_title())); ?>">
           <?php esc_html_e('READ', 'toocheke-companion'); ?>
        </a>
    </div>
</div>
