<?php
/**
 * Extends the site's RSS feeds with comic-specific metadata and images,
 * and registers the dedicated per-series and per-manga-series feed URLs and
 * the manga series REST route.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_RSS_Feeds
{
            public function toocheke_feed_post_status($query)
            {
                $combine_rss = get_option('toocheke-comics-to-main-rss') && 1 == get_option('toocheke-comics-to-main-rss');
                if (! $query->is_feed() || ! $combine_rss) {
                    return; // this isn't a feed, abort!
                }
                $query->set('post_status', 'publish'); // we only want published posts, no drafts or private
            }

            public function toocheke_feed_request($qv)
            {
                $combine_rss = get_option('toocheke-comics-to-main-rss') && 1 == get_option('toocheke-comics-to-main-rss');
                if (isset($qv['feed']) && ! isset($qv['post_type']) && $combine_rss) {
                    $qv['post_type'] = ['post', 'comic'];
                }

                return $qv;
            }

            public function toocheke_add_metadata_to_rss($content)
            {
                $combine_rss = get_option('toocheke-comics-to-main-rss') && 1 == get_option('toocheke-comics-to-main-rss');
                if (is_feed() && $combine_rss) {
                    global $post;
                    $postid = $post->ID;
                    if ($postid) {
                        $custom_metadata = get_post_meta($postid, 'comic_blog_post_editor', true);
                        if ($custom_metadata !== '') {
                            // Display custom field data below content
                            $content = $content . "<br /><br /><div>" . $custom_metadata . "</div>";
                        } else {
                            $content = $content;
                        }
                    }
                }
                return $content;
            }

            public function toocheke_add_rss_namespaces()
            {
                echo 'xmlns:toocheke="https://toocheke.com/rss/"';
            }

            public function toocheke_add_comic_images_to_rss()
            {
                global $post;

                if (!in_array(get_post_type($post), ['comic', 'manga_chapter'])) {
                    return;
                }

                /*
                * Social Share Image (preferred) or Global Social Share fallback, then Featured Image fallback
                */

                $thumbnail_id = get_post_thumbnail_id($post->ID);

                $raw_social_id = get_post_meta($post->ID, 'comicscout_social_share_image_id', true);

                // Determine image ID and source
                if (!empty($raw_social_id)) {
                    $social_share_image_id = $raw_social_id;
                    $social_source         = 'social_share';
                    $social_is_fallback    = false;
                } else {
                    // Check for global social share image
                    $global_social_id = get_option('toocheke-comicscout-global-social-share-image');
                    $global_social_id = $global_social_id ? (int) $global_social_id : 0;

                    if (!empty($global_social_id)) {
                        $social_share_image_id = $global_social_id;
                        $social_source         = 'default_social_share';
                        $social_is_fallback    = false;
                    } else {
                        $social_share_image_id = $thumbnail_id;
                        $social_source         = 'featured';
                        $social_is_fallback    = true;
                    }
                }

                if ($social_share_image_id) {

                    $image_url  = wp_get_attachment_image_url($social_share_image_id, 'full');
                    $image_meta = wp_get_attachment_metadata($social_share_image_id);
                    $mime_type  = get_post_mime_type($social_share_image_id);

                    $file_path = get_attached_file($social_share_image_id);
                    $length    = ($file_path && file_exists($file_path)) ? filesize($file_path) : 0;

                    if ($image_url) {

                        $width  = !empty($image_meta['width'])  ? (int) $image_meta['width']  : 0;
                        $height = !empty($image_meta['height']) ? (int) $image_meta['height'] : 0;

                        $aspect = ($width > 0 && $height > 0)
                            ? round($width / $height, 4)
                            : '';

                        echo '<enclosure url="' . esc_url($image_url) . '" length="' . esc_attr($length) . '" type="' . esc_attr($mime_type) . '" />' . "\n";

                        echo '<toocheke:featured_image'
                            . ' url="'          . esc_url($image_url)                              . '"'
                            . ' type="'         . esc_attr($mime_type)                             . '"'
                            . ' width="'        . esc_attr($width)                                 . '"'
                            . ' height="'       . esc_attr($height)                                . '"'
                            . ' aspect_ratio="' . esc_attr($aspect)                                . '"'
                            . ' fallback="'     . esc_attr($social_is_fallback ? 'true' : 'false') . '"'
                            . ' source="'       . esc_attr($social_source)                         . '"'
                            . ' />' . "\n";
                    }
                }

                /*
                * ComicScout Thumbnail
                */

                $raw_comicscout_id      = get_post_meta($post->ID, 'comicscout_image_id', true);
                $comicscout_is_fallback = empty($raw_comicscout_id);

                $comicscout_image_id = $raw_comicscout_id ? $raw_comicscout_id : $thumbnail_id;
                $comicscout_source   = $comicscout_is_fallback ? 'featured' : 'comicscout';

                if ($comicscout_image_id) {

                    $comicscout_image_url = wp_get_attachment_image_url($comicscout_image_id, 'full');
                    $comicscout_meta      = wp_get_attachment_metadata($comicscout_image_id);
                    $comicscout_mime_type = get_post_mime_type($comicscout_image_id);

                    if ($comicscout_image_url) {

                        $comicscout_width  = !empty($comicscout_meta['width'])  ? (int) $comicscout_meta['width']  : 0;
                        $comicscout_height = !empty($comicscout_meta['height']) ? (int) $comicscout_meta['height'] : 0;

                        $comicscout_aspect = ($comicscout_width > 0 && $comicscout_height > 0)
                            ? round($comicscout_width / $comicscout_height, 4)
                            : '';

                        echo '<toocheke:comicscout_thumbnail'
                            . ' url="'          . esc_url($comicscout_image_url)                          . '"'
                            . ' type="'         . esc_attr($comicscout_mime_type)                         . '"'
                            . ' width="'        . esc_attr($comicscout_width)                             . '"'
                            . ' height="'       . esc_attr($comicscout_height)                            . '"'
                            . ' aspect_ratio="' . esc_attr($comicscout_aspect)                            . '"'
                            . ' fallback="'     . esc_attr($comicscout_is_fallback ? 'true' : 'false')    . '"'
                            . ' source="'       . esc_attr($comicscout_source)                            . '"'
                            . ' />' . "\n";
                    }
                }
            }

            /**
             * Add sid querystring to comic links in the RSS feed
             */
            public function toocheke_add_series_id_to_rss_permalink($permalink) {
                if (!is_feed()) {
                    return $permalink;
                }

                global $post;
                if (!$post || $post->post_type !== 'comic') {
                    return $permalink;
                }

                $series_id = $post->post_parent;
                if ($series_id > 0) {
                    $permalink = add_query_arg('sid', $series_id, $permalink);
                }

                return $permalink;
            }

            /**
             * Register series_feed query var
             */
            public function toocheke_series_feed_query_vars($vars) {
                $vars[] = 'series_feed';
                return $vars;
            }

            /**
             * Intercept the series feed and output RSS2
             */
            public function toocheke_series_feed_redirect() {
                if (!get_query_var('series_feed')) {
                    return;
                }

                // Get the series post by slug
                $series_slug = get_query_var('name');
                if (!$series_slug) {
                    return;
                }

                $series = get_page_by_path($series_slug, OBJECT, 'series');
                if (!$series) {
                    global $wp_query;
                    $wp_query->set_404();
                    status_header(404);
                    return;
                }

                $series_id    = $series->ID;
                $series_title = get_the_title($series_id);
                $series_url   = get_permalink($series_id);
                $series_desc  = has_excerpt($series_id)
                    ? get_the_excerpt($series_id)
                    : get_bloginfo('description');

                // Query comics belonging to this series
                $comics = new WP_Query([
                    'post_type'      => 'comic',
                    'post_parent'    => $series_id,
                    'post_status'    => 'publish',
                    'posts_per_page' => get_option('posts_per_rss', 10),
                    'orderby'        => 'post_date',
                    'order'          => 'DESC',
                ]);

                // Output RSS
                header('Content-Type: application/rss+xml; charset=' . get_bloginfo('charset'), true);
                header('X-Robots-Tag: noindex, follow', true);
                echo '<?xml version="1.0" encoding="' . esc_attr(get_bloginfo('charset')) . '"?>';
                ?>
            <rss version="2.0"
                xmlns:content="http://purl.org/rss/1.0/modules/content/"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:atom="http://www.w3.org/2005/Atom"
                xmlns:toocheke="https://toocheke.com/rss/">
            <channel>
                <title><?php echo esc_html($series_title); ?> - <?php echo esc_html(get_bloginfo('name')); ?></title>
                <atom:link href="<?php echo esc_url(home_url('/series/' . $series_slug . '/feed/')); ?>" rel="self" type="application/rss+xml" />
                <link><?php echo esc_url($series_url); ?></link>
                <description><?php echo esc_html($series_desc); ?></description>
                <lastBuildDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', get_lastpostdate('GMT', 'comic'), false)); ?></lastBuildDate>
                <language><?php echo esc_html(get_bloginfo('language')); ?></language>
                <?php
                if ($comics->have_posts()):
                    while ($comics->have_posts()):
                        $comics->the_post();
                        $post_id        = get_the_ID();
                        $comic_url      = add_query_arg('sid', $series_id, get_permalink($post_id));
                        $pub_date       = mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false);
                        $raw_content = get_the_content();
                        $content     = apply_filters('the_content', $raw_content);

                        // Append comic_blog_post_editor meta, same as toocheke_add_metadata_to_rss does for the main feed
                        $blog_post_meta = get_post_meta($post_id, 'comic_blog_post_editor', true);
                        if (!empty($blog_post_meta)) {
                            $content .= '<br /><br /><div>' . $blog_post_meta . '</div>';
                        }

                        $excerpt = get_the_excerpt();

                        // Append blog post meta to excerpt too, same as the_excerpt_rss filter does
                        if (!empty($blog_post_meta)) {
                            $excerpt .= '<br /><br /><div>' . $blog_post_meta . '</div>';
                        }
                        ?>
                <item>
                    <title><?php echo esc_html(get_the_title()); ?></title>
                    <link><?php echo esc_url($comic_url); ?></link>
                    <guid isPermaLink="false"><?php echo esc_url(get_the_guid($post_id)); ?></guid>
                    <dc:creator><?php echo esc_html(get_the_author()); ?></dc:creator>
                    <pubDate><?php echo esc_html($pub_date); ?></pubDate>
                    <description><![CDATA[<?php echo $excerpt; ?>]]></description>
                    <content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
                    <?php $this->toocheke_series_feed_output_images($post_id); ?>
                </item>
                    <?php
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </channel>
            </rss>
                <?php
                exit;
            }

            /**
             * Register manga_series_feed query var
             */
            public function toocheke_manga_series_feed_query_vars($vars) {
                $vars[] = 'manga_series_feed';
                return $vars;
            }

            /**
             * Intercept the manga series feed and output RSS2
             */
            public function toocheke_manga_series_feed_redirect() {
                if (!get_query_var('manga_series_feed')) {
                    return;
                }

                $series_slug = get_query_var('name');
                if (!$series_slug) {
                    return;
                }

                $manga_series = get_page_by_path($series_slug, OBJECT, 'manga_series');
                if (!$manga_series) {
                    global $wp_query;
                    $wp_query->set_404();
                    status_header(404);
                    return;
                }

                $series_id    = $manga_series->ID;
                $series_title = get_the_title($series_id);
                $series_url   = get_permalink($series_id);
                $series_desc  = has_excerpt($series_id)
                    ? get_the_excerpt($series_id)
                    : get_bloginfo('description');

                // Query manga chapters belonging to this series via series_id meta
                $chapters = new WP_Query([
                    'post_type'      => 'manga_chapter',
                    'post_status'    => 'publish',
                    'posts_per_page' => get_option('posts_per_rss', 10),
                    'orderby'        => 'post_date',
                    'order'          => 'DESC',
                    'meta_query'     => [
                        [
                            'key'     => 'series_id',
                            'value'   => $series_id,
                            'compare' => '=',
                            'type'    => 'NUMERIC',
                        ],
                    ],
                ]);

                header('Content-Type: application/rss+xml; charset=' . get_bloginfo('charset'), true);
                header('X-Robots-Tag: noindex, follow', true);
                echo '<?xml version="1.0" encoding="' . esc_attr(get_bloginfo('charset')) . '"?>';
                ?>
            <rss version="2.0"
                xmlns:content="http://purl.org/rss/1.0/modules/content/"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:atom="http://www.w3.org/2005/Atom"
                xmlns:toocheke="https://toocheke.com/rss/">
            <channel>
                <title><?php echo esc_html($series_title); ?> - <?php echo esc_html(get_bloginfo('name')); ?></title>
                <atom:link href="<?php echo esc_url(home_url('/manga_series/' . $series_slug . '/feed/')); ?>" rel="self" type="application/rss+xml" />
                <link><?php echo esc_url($series_url); ?></link>
                <description><?php echo esc_html($series_desc); ?></description>
                <lastBuildDate><?php echo esc_html(mysql2date('D, d M Y H:i:s +0000', get_lastpostdate('GMT', 'manga_chapter'), false)); ?></lastBuildDate>
                <language><?php echo esc_html(get_bloginfo('language')); ?></language>
                <?php
                if ($chapters->have_posts()):
                    while ($chapters->have_posts()):
                        $chapters->the_post();
                        $post_id      = get_the_ID();
                        $chapter_url  = get_permalink($post_id);
                        $pub_date     = mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false);

                        // manga_chapter has no editor support; use notes meta as content
                        $notes   = get_post_meta($post_id, 'notes', true);
                        $content = !empty($notes) ? '<p>' . nl2br(esc_html($notes)) . '</p>' : '';
                        $excerpt = !empty($notes) ? wp_trim_words($notes, 55) : '';
                        ?>
                <item>
                    <title><?php echo esc_html(get_the_title()); ?></title>
                    <link><?php echo esc_url($chapter_url); ?></link>
                    <guid isPermaLink="false"><?php echo esc_url(get_the_guid($post_id)); ?></guid>
                    <dc:creator><?php echo esc_html(get_the_author()); ?></dc:creator>
                    <pubDate><?php echo esc_html($pub_date); ?></pubDate>
                    <description><![CDATA[<?php echo $excerpt; ?>]]></description>
                    <content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
                    <?php $this->toocheke_manga_series_feed_output_images($post_id); ?>
                </item>
                    <?php
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </channel>
            </rss>
                <?php
                exit;
            }

            /**
             * Output enclosure and toocheke image elements for a manga chapter in the manga series feed
             */
            private function toocheke_manga_series_feed_output_images($post_id) {
                $thumbnail_id  = get_post_thumbnail_id($post_id);
                $raw_social_id = get_post_meta($post_id, 'comicscout_social_share_image_id', true);

                // Determine social share image — same fallback chain as comic feed
                if (!empty($raw_social_id)) {
                    $social_share_image_id = $raw_social_id;
                    $social_source         = 'social_share';
                    $social_is_fallback    = false;
                } else {
                    $global_social_id = get_option('toocheke-comicscout-global-social-share-image');
                    $global_social_id = $global_social_id ? (int) $global_social_id : 0;

                    if (!empty($global_social_id)) {
                        $social_share_image_id = $global_social_id;
                        $social_source         = 'default_social_share';
                        $social_is_fallback    = false;
                    } else {
                        $social_share_image_id = $thumbnail_id;
                        $social_source         = 'featured';
                        $social_is_fallback    = true;
                    }
                }

                if ($social_share_image_id) {
                    $image_url  = wp_get_attachment_image_url($social_share_image_id, 'full');
                    $image_meta = wp_get_attachment_metadata($social_share_image_id);
                    $mime_type  = get_post_mime_type($social_share_image_id);
                    $file_path  = get_attached_file($social_share_image_id);
                    $length     = ($file_path && file_exists($file_path)) ? filesize($file_path) : 0;

                    if ($image_url) {
                        $width  = !empty($image_meta['width'])  ? (int) $image_meta['width']  : 0;
                        $height = !empty($image_meta['height']) ? (int) $image_meta['height'] : 0;
                        $aspect = ($width > 0 && $height > 0) ? round($width / $height, 4) : '';

                        echo '<enclosure url="' . esc_url($image_url) . '" length="' . esc_attr($length) . '" type="' . esc_attr($mime_type) . '" />' . "\n";
                        echo '<toocheke:featured_image'
                            . ' url="'          . esc_url($image_url)                              . '"'
                            . ' type="'         . esc_attr($mime_type)                             . '"'
                            . ' width="'        . esc_attr($width)                                 . '"'
                            . ' height="'       . esc_attr($height)                                . '"'
                            . ' aspect_ratio="' . esc_attr($aspect)                                . '"'
                            . ' fallback="'     . esc_attr($social_is_fallback ? 'true' : 'false') . '"'
                            . ' source="'       . esc_attr($social_source)                         . '"'
                            . ' />' . "\n";
                    }
                }

                // ComicScout thumbnail
                $raw_comicscout_id      = get_post_meta($post_id, 'comicscout_image_id', true);
                $comicscout_is_fallback = empty($raw_comicscout_id);
                $comicscout_image_id    = $raw_comicscout_id ? $raw_comicscout_id : $thumbnail_id;
                $comicscout_source      = $comicscout_is_fallback ? 'featured' : 'comicscout';

                if ($comicscout_image_id) {
                    $comicscout_image_url = wp_get_attachment_image_url($comicscout_image_id, 'full');
                    $comicscout_meta      = wp_get_attachment_metadata($comicscout_image_id);
                    $comicscout_mime_type = get_post_mime_type($comicscout_image_id);

                    if ($comicscout_image_url) {
                        $comicscout_width  = !empty($comicscout_meta['width'])  ? (int) $comicscout_meta['width']  : 0;
                        $comicscout_height = !empty($comicscout_meta['height']) ? (int) $comicscout_meta['height'] : 0;
                        $comicscout_aspect = ($comicscout_width > 0 && $comicscout_height > 0)
                            ? round($comicscout_width / $comicscout_height, 4) : '';

                        echo '<toocheke:comicscout_thumbnail'
                            . ' url="'          . esc_url($comicscout_image_url)                       . '"'
                            . ' type="'         . esc_attr($comicscout_mime_type)                      . '"'
                            . ' width="'        . esc_attr($comicscout_width)                          . '"'
                            . ' height="'       . esc_attr($comicscout_height)                         . '"'
                            . ' aspect_ratio="' . esc_attr($comicscout_aspect)                         . '"'
                            . ' fallback="'     . esc_attr($comicscout_is_fallback ? 'true' : 'false') . '"'
                            . ' source="'       . esc_attr($comicscout_source)                         . '"'
                            . ' />' . "\n";
                    }
                }
            }

            /**
             * Output enclosure and toocheke image elements for a comic in the series feed
             */
            private function toocheke_series_feed_output_images($post_id) {
                $thumbnail_id  = get_post_thumbnail_id($post_id);
                $raw_social_id = get_post_meta($post_id, 'comicscout_social_share_image_id', true);

                // Determine social share image
                if (!empty($raw_social_id)) {
                    $social_share_image_id = $raw_social_id;
                    $social_source         = 'social_share';
                    $social_is_fallback    = false;
                } else {
                    $global_social_id = get_option('toocheke-comicscout-global-social-share-image');
                    $global_social_id = $global_social_id ? (int) $global_social_id : 0;

                    if (!empty($global_social_id)) {
                        $social_share_image_id = $global_social_id;
                        $social_source         = 'default_social_share';
                        $social_is_fallback    = false;
                    } else {
                        $social_share_image_id = $thumbnail_id;
                        $social_source         = 'featured';
                        $social_is_fallback    = true;
                    }
                }

                if ($social_share_image_id) {
                    $image_url  = wp_get_attachment_image_url($social_share_image_id, 'full');
                    $image_meta = wp_get_attachment_metadata($social_share_image_id);
                    $mime_type  = get_post_mime_type($social_share_image_id);
                    $file_path  = get_attached_file($social_share_image_id);
                    $length     = ($file_path && file_exists($file_path)) ? filesize($file_path) : 0;

                    if ($image_url) {
                        $width  = !empty($image_meta['width'])  ? (int) $image_meta['width']  : 0;
                        $height = !empty($image_meta['height']) ? (int) $image_meta['height'] : 0;
                        $aspect = ($width > 0 && $height > 0) ? round($width / $height, 4) : '';

                        echo '<enclosure url="' . esc_url($image_url) . '" length="' . esc_attr($length) . '" type="' . esc_attr($mime_type) . '" />' . "\n";
                        echo '<toocheke:featured_image'
                            . ' url="'          . esc_url($image_url)                              . '"'
                            . ' type="'         . esc_attr($mime_type)                             . '"'
                            . ' width="'        . esc_attr($width)                                 . '"'
                            . ' height="'       . esc_attr($height)                                . '"'
                            . ' aspect_ratio="' . esc_attr($aspect)                                . '"'
                            . ' fallback="'     . esc_attr($social_is_fallback ? 'true' : 'false') . '"'
                            . ' source="'       . esc_attr($social_source)                         . '"'
                            . ' />' . "\n";
                    }
                }

                // ComicScout thumbnail
                $raw_comicscout_id      = get_post_meta($post_id, 'comicscout_image_id', true);
                $comicscout_is_fallback = empty($raw_comicscout_id);
                $comicscout_image_id    = $raw_comicscout_id ? $raw_comicscout_id : $thumbnail_id;
                $comicscout_source      = $comicscout_is_fallback ? 'featured' : 'comicscout';

                if ($comicscout_image_id) {
                    $comicscout_image_url = wp_get_attachment_image_url($comicscout_image_id, 'full');
                    $comicscout_meta      = wp_get_attachment_metadata($comicscout_image_id);
                    $comicscout_mime_type = get_post_mime_type($comicscout_image_id);

                    if ($comicscout_image_url) {
                        $comicscout_width  = !empty($comicscout_meta['width'])  ? (int) $comicscout_meta['width']  : 0;
                        $comicscout_height = !empty($comicscout_meta['height']) ? (int) $comicscout_meta['height'] : 0;
                        $comicscout_aspect = ($comicscout_width > 0 && $comicscout_height > 0)
                            ? round($comicscout_width / $comicscout_height, 4) : '';

                        echo '<toocheke:comicscout_thumbnail'
                            . ' url="'          . esc_url($comicscout_image_url)                       . '"'
                            . ' type="'         . esc_attr($comicscout_mime_type)                      . '"'
                            . ' width="'        . esc_attr($comicscout_width)                          . '"'
                            . ' height="'       . esc_attr($comicscout_height)                         . '"'
                            . ' aspect_ratio="' . esc_attr($comicscout_aspect)                         . '"'
                            . ' fallback="'     . esc_attr($comicscout_is_fallback ? 'true' : 'false') . '"'
                            . ' source="'       . esc_attr($comicscout_source)                         . '"'
                            . ' />' . "\n";
                    }
                }
            }

            public function toocheke_block_manga_chapter_archive(){
                   if (is_post_type_archive('manga_chapter') && !is_feed()) {
                        global $wp_query;
                        $wp_query->set_404();
                        status_header(404);
                        nocache_headers();
                        return;
                    }
            }

/**
     * Register REST API route for manga series infinite scroll
     */
    public function toocheke_register_manga_series_rest_route()
    {
        register_rest_route('toocheke/v1', '/manga-series', [
            'methods'             => 'GET',
            'callback'            => [$this, 'toocheke_manga_series_rest_callback'],
            'permission_callback' => '__return_true',
            'args'                => [
                'page'      => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page'  => [
                    'default'           => 12,
                    'sanitize_callback' => 'absint',
                ],
                'publisher' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'genre'     => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Callback for the manga series REST API endpoint
     */
    public function toocheke_manga_series_rest_callback(WP_REST_Request $request)
    {
        $page      = $request->get_param('page');
        $per_page  = $request->get_param('per_page');
        $publisher = $request->get_param('publisher');
        $genre     = $request->get_param('genre');

        $args = [
            'post_type'      => 'manga_series',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $tax_query = [];

        if ($publisher) {
            $tax_query[] = [
                'taxonomy' => 'manga_publisher',
                'field'    => 'slug',
                'terms'    => $publisher,
            ];
        }

        if ($genre) {
            $tax_query[] = [
                'taxonomy' => 'manga_genre',
                'field'    => 'slug',
                'terms'    => $genre,
            ];
        }

        if ($tax_query) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
        }

        $query  = new WP_Query($args);
        $series = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $post_id          = get_the_ID();
                $thumbnail_id     = get_post_thumbnail_id($post_id);
                $thumbnail_url    = '';
                $thumbnail_srcset = '';
                $thumbnail_sizes  = '';

                if ($thumbnail_id) {
                    $full             = wp_get_attachment_image_src($thumbnail_id, 'full');
                    $thumbnail_url    = $full ? $full[0] : '';
                    $thumbnail_srcset = wp_get_attachment_image_srcset($thumbnail_id, 'full') ?: '';
                    $thumbnail_sizes  = wp_get_attachment_image_sizes($thumbnail_id, 'full') ?: '';
                }

                $series[] = [
                    'id'               => $post_id,
                    'title' => get_post_field( 'post_title', $post_id ),
                    'permalink'        => get_permalink(),
                    'thumbnail'        => $thumbnail_url,
                    'thumbnail_srcset' => $thumbnail_srcset,
                    'thumbnail_sizes'  => $thumbnail_sizes,
                ];
            }
            wp_reset_postdata();
        }

        return rest_ensure_response([
            'series'     => $series,
            'totalPages' => (int) $query->max_num_pages,
            'page'       => $page,
        ]);
    }

}
