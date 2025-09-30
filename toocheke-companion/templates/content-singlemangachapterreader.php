<?php
    /**
     * Template part for displaying the reader for the a single manga volume post(single-manga_volume.php)
     *
     * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
     *
     * @package Toocheke
     */
    //intiailize volume variables
    $display_likes                = get_option('toocheke-comic-likes') && 1 == get_option('toocheke-comic-likes');
    $display_no_views             = get_option('toocheke-comic-no-of-views') && 1 == get_option('toocheke-comic-no-of-views');
    $manga_chapter_id = get_the_ID();
    $manga_volume_id    = get_post_meta($manga_chapter_id, 'volume_id', true);
    $volume_permalink = get_permalink( $manga_volume_id );
    $chapters_query = null;

    if ($manga_volume_id) {
    $chapters_query = new WP_Query([
        'post_type'      => 'manga_chapter',
        'posts_per_page' => -1,
        'meta_key'       => 'chapter_number',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'   => 'volume_id',
                'value' => $manga_volume_id,
            ],
        ],
    ]);


}
?>
 <!--MANGA TOP NAV-->
<div id="manga-page-top-nav" class="manga-page-nav">
    <a href="<?php echo esc_url($volume_permalink);?>" title="Close reader" class="close-reader"><svg viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg"><title></title><path d="M69.8437,43.3876,33.8422,13.3863a6.0035,6.0035,0,0,0-7.6878,9.223l30.47,25.39-30.47,25.39a6.0035,6.0035,0,0,0,7.6878,9.2231L69.8437,52.6106a6.0091,6.0091,0,0,0,0-9.223Z"></path></svg> Close reader</a>
</div>
   <!--./MANGA TOP NAV-->

                     <!--start content-->
                         <div class="manga-chapter-container manga-reader-container two-pages">
                  
    <?php
    // Get swipe direction setting



        $all_images = get_post_meta($manga_chapter_id, 'manga_chapter_pages', true);

      
        if (!empty($all_images) && is_array($all_images)): ?>
           <div id="swiper-loader-container">
    <div class="spinner"></div>
</div>
            <swiper-container id="manga-swiper" class="swiper"   pagination="true" pagination-type="progressbar" navigation="true" space-between="0" slides-per-view="2" slides-per-group="2"  keyboard="true" events-prefix="swiper-" breakpoints='{"0":{"direction":"vertical","slidesPerView":1},"768":{"direction":"horizontal","slidesPerView":2}}'>

                   <?php foreach ($all_images as $img_id): 
                        $img_url = wp_get_attachment_image_url($img_id, 'full'); ?>
                        <?php if ($img_url): ?>
                            <swiper-slide>
                                
                                <img src="<?php echo esc_url($img_url); ?>" alt="" />
                                </swiper-slide>
                        <?php endif; ?>
                    <?php endforeach; ?>
<swiper-slide><a href="<?php echo esc_url($volume_permalink);?>" class="btn-manga-close-reader btn btn-dark">Close Reader</a></swiper-slide>
               </swiper-container>

            
        <?php endif;
    
    ?>
</div>
   <!--MANGA BOTTOM NAV-->
<div id="manga-page-bottom-nav" class="manga-page-nav">
<div id="manga-meta-nav">
    <div id="manga-title-bar">
    <div class="chapter-title"><?php echo get_the_title(); ?></div>
    <div class="page-wrapper"><span class="swiper-pagination">&nbsp;</span></div>
    </div>

<div id="manga-comic-actions">
<div class="manga-nav-wrapper">
     <?php _e('Browse Chapters', 'toocheke-companion'); ?>
<?php 
if ($chapters_query->have_posts()) : ?>
        <select onchange="document.location.href=this.options[this.selectedIndex].value" class="manga-dropdown">
            <?php while ($chapters_query->have_posts()) : $chapters_query->the_post(); ?>
                <option value="<?php the_permalink(); ?>"
                    <?php selected(get_the_ID(), $manga_chapter_id); ?>>
                    <?php the_title(); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <?php wp_reset_postdata();
    endif;
?>
 <?php
    if ($display_likes):
?>
<hr />
<p>
		            <b>Like this:</b> <span class="single-comic-total-likes">
		               <?php echo do_shortcode("[toocheke-like-button]"); ?>
		</span>
        </p>
		<?php
            endif;
        ?>
</div>
<div id="manga-page-options">
<div id="btn-one-page" class="page-icons-wrapper one-page"><div class="icon-wrapper"><svg viewBox="0 0 25 50" fill="none" xmlns="http://www.w3.org/2000/svg" width="55" height="70"><path stroke-width="2" stroke="#000" d="M.5.5h24v49H.5z"></path></svg></div><span class="icon-text">1 page</span></div>
<div id="btn-two-pages" class="page-icons-wrapper two-pages"><div class="make-two-pages"><svg viewBox="0 0 25 50" fill="none" xmlns="http://www.w3.org/2000/svg" width="35" height="70" class="two-pages-icon"><path stroke-width="2" stroke="#000" d="M.5.5h24v49H.5z"></path></svg><svg viewBox="0 0 25 50" fill="none" xmlns="http://www.w3.org/2000/svg" width="35" height="70" class="two-pages-icon"><path stroke-width="2" stroke="#000" d="M.5.5h24v49H.5z"></path></svg></div><span class="icon-text">2 pages</span></div>
<div id="btn-fullscreen" class="page-icons-wrapper fullscreen"><svg id="fullsreen-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="70" height="70" class="fullscreen-icon right"><defs><style>.full-screen-path{fill:#fff}</style></defs><path class="full-screen-path" d="M0 0v128h32V32h96V0H0Zm32 480v-96H0v128h128v-32H32ZM384 0v32h96v96h32V0H384Zm96 384v96h-96v32h128V384h-32Z"></path><path class="full-screen-path" transform="rotate(45 256.002 256)" d="M-77.28 240.83h666.57v30.33H-77.28z"></path><path class="full-screen-path" transform="rotate(-45 256 256.002)" d="M-77.28 240.83h666.57v30.33H-77.28z"></path></svg><span class="icon-text">Fullscreen</span></div>
</div>
</div>

</div>

</div>
   <!--./MANGA BOTTOM NAV-->
              <!--end content-->
