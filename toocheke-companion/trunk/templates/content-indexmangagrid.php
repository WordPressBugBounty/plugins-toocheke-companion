<?php
/**
 * Template Part: Manga Grid Item
 *
 * Variables passed:
 *   $query       : WP_Query object for this section
 *   $section_id  : HTML id for the section
 *   $section_title : Title to display
 */
$query = $args['query'] ?? null;
$section_id = $args['section_id'] ?? '';
$section_title = $args['section_title'] ?? '';
$nothing_found_text = !empty($section_title) ? $section_title : __('Series', 'toocheke-companion');
if (! isset($query) || ! $query instanceof WP_Query) {
    return;
}
?>

<?php if ($query->have_posts()) : ?>
    <?php if($section_id && $section_title):?>
    <h2 id="<?php echo esc_attr($section_id); ?>">
        <?php echo esc_html($section_title); ?>
    </h2>
    <hr class="toocheke-hr manga-hr" />
    <?php endif;?>
    
    <div class="manga-grid-list-container">
        <?php while ($query->have_posts()) : $query->the_post(); ?>
            <a href="<?php the_permalink(); ?>" class="manga-grid-item-container fade-in" title="<?php the_title_attribute(); ?>">
                <div class="manga-grid-item-thumbnail manga-thumbnail">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('full'); ?>
                    <?php else : ?>
                        <img src="<?php echo esc_url(plugins_url('toocheke-companion/img/no-image.png')); ?>" alt="<?php the_title_attribute(); ?>" />
                    <?php endif; ?>
                </div>
                <span class="manga-grid-item-title"><?php the_title(); ?></span>
            </a>
        <?php endwhile; ?>
    </div>
     <hr class="toocheke-hr manga-hr" />
    <?php wp_reset_postdata(); ?>
<?php else: ?>
    <p class="font-weight-bold">No <?php echo esc_html($nothing_found_text); ?> found.</p>
<?php endif; ?>
