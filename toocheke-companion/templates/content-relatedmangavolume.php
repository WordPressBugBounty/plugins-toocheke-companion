<?php
    /**
     * Template part for displaying a related volume
     *
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Toocheke
     */
    $manga_volume_id = get_the_ID();
$release_date    = get_post_meta($manga_volume_id, 'release_date', true);
$pages           = get_post_meta($manga_volume_id, 'pages', true);

$formatted_release_date = $release_date ? (new DateTime($release_date))->format('M. d, Y') : '';

$buy_digital_url = get_post_meta($manga_volume_id, 'buy_digital_url', true);
$buy_print_url   = get_post_meta($manga_volume_id, 'buy_print_url', true);
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
            <span><?php echo sprintf(_n('%s page', '%s pages', $pages, 'toocheke-companion'), esc_html($pages)); ?></span>
        </div>

        <a href="<?php echo esc_url(add_query_arg('reader', 'true', get_permalink())); ?>"
           title="<?php printf(esc_attr__('Read %s', 'toocheke-companion'), get_the_title()); ?>">
           <?php _e('READ', 'toocheke-companion'); ?>
        </a>

        <div class="manga-volume-purchase-options">
            <?php
            $buy_links = [
                'digital' => ['url' => $buy_digital_url, 'label' => __('Buy Digital', 'toocheke-companion'), 'title' => __('Buy Digital Copy', 'toocheke-companion')],
                'print'   => ['url' => $buy_print_url,   'label' => __('Buy Print', 'toocheke-companion'),   'title' => __('Buy Print Copy', 'toocheke-companion')],
            ];

            $site_host = parse_url(home_url(), PHP_URL_HOST);

            foreach ($buy_links as $link) {
                if (!empty($link['url'])) {
                    $link_host   = parse_url($link['url'], PHP_URL_HOST);
                    $is_external = $link_host && $link_host !== $site_host;
                    ?>
                    <a class="btn btn-outline-black btn-sm btn-manga-volume-buy-link"
                       title="<?php echo esc_attr($link['title']); ?>"
                       href="<?php echo esc_url($link['url']); ?>"
                       <?php if ($is_external) echo 'target="_blank" rel="noopener"'; ?>>
                        <?php echo esc_html($link['label']); ?>
                    </a><br/>
                <?php }
            } ?>
        </div>
    </div>
</div>