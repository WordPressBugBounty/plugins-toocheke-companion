<?php
/**
 * Implements the comic 'like' feature: the AJAX handlers for logged-in and
 * logged-out visitors, the like button shortcode/markup, like counting, and
 * the 'liked comics' list shown on a user's profile.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Likes
{
            /**
             * Likes Functionality
             */
            public function toocheke_process_like()
            {
                // Check for bot User-Agent
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                if (empty($user_agent)) {
                    wp_send_json_error('No user agent detected');
                }

                // Comprehensive bot detection pattern
                $bot_patterns = [
                    // Traditional bots
                    'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
                    
                    // AI Bots
                    'gptbot',           // OpenAI ChatGPT
                    'chatgpt',          // ChatGPT variations
                    'claude',           // Anthropic Claude
                    'claudebot',        // Claude's crawler
                    'google-extended',  // Google Bard/Gemini
                    'bard',             // Google Bard
                    'gemini',           // Google Gemini
                    'copilot',          // Microsoft Copilot
                    'bingbot',          // Bing (used by Copilot)
                    'perplexity',       // Perplexity AI
                    'perplexitybot',    // Perplexity crawler
                    'anthropic',        // Anthropic variations
                    'cohere',           // Cohere AI
                    'you\.com',         // You.com AI
                    'meta-externalagent', // Meta AI
                    'facebookbot',      // Facebook/Meta
                    'applebot',         // Apple Intelligence
                    'amazonbot',        // Amazon AI
                    'diffbot',          // Diffbot AI
                    'bytespider',       // ByteDance (TikTok)
                    'ccbot',            // Common Crawl (used by many AI)
                    'omgili',           // Omgili bot
                    'petalbot',         // Huawei AI
                    'yandex',           // Yandex AI
                    'ia_archiver',      // Internet Archive
                    'semrush',          // SEMrush bot
                    'ahrefsbot',        // Ahrefs
                    'mj12bot',          // Majestic
                    'dotbot',           // SEO bot
                    'scrapy',           // Scrapy framework
                    'python-requests',  // Common scraping library
                    'axios',            // HTTP client (often used in automation)
                    'curl',             // Command line tool
                    'wget',             // Download tool
                    'headless',         // Headless browsers
                    'phantom',          // PhantomJS
                    'selenium',         // Selenium automation
                    'webdriver',        // Browser automation
                ];

                $pattern = '/(' . implode('|', $bot_patterns) . ')/i';

                if (preg_match($pattern, $user_agent)) {
                    wp_send_json_error('Bot detected');
                }

                // Also check for suspicious patterns in the user agent
                $suspicious_patterns = [
                    '/^(python|java|ruby|perl|php)\//i',  // Programming language clients
                    '/^$/i',                                // Empty user agents
                    '/^mozilla\/[0-9]\.[0-9]$/i',          // Minimal Mozilla strings
                ];

                foreach ($suspicious_patterns as $pattern) {
                    if (preg_match($pattern, $user_agent)) {
                        wp_send_json_error('Suspicious user agent detected');
                    }
                }

                // Security
                $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : 0;
                if (! wp_verify_nonce($nonce, 'toocheke-likes-nonce')) {
                    exit(__('Not permitted', 'toocheke-companion'));
                }
                // Test if javascript is disabled
                $disabled = (isset($_REQUEST['disabled']) && $_REQUEST['disabled'] == true) ? true : false;
                // Test if this is a comment
                $is_comment = (isset($_REQUEST['is_comment']) && $_REQUEST['is_comment'] == 1) ? 1 : 0;
                // Base variables
                $post_id    = (isset($_REQUEST['post_id']) && is_numeric($_REQUEST['post_id'])) ? $_REQUEST['post_id'] : '';
                $result     = [];
                $post_users = null;
                $like_count = 0;
                // Get plugin options
                if ($post_id != '') {
                    $count = ($is_comment == 1) ? get_comment_meta($post_id, "_comment_like_count", true) : get_post_meta($post_id, "_post_like_count", true); // like count
                    $count = (isset($count) && is_numeric($count)) ? $count : 0;
                    if (! $this->toocheke_check_if_liked($post_id, $is_comment)) { // Like the post
                        if (is_user_logged_in()) {                                    // user is logged in
                            $user_id    = get_current_user_id();
                            $post_users = $this->toocheke_post_user_likes($user_id, $post_id, $is_comment);
                            if ($is_comment == 1) {
                                // Update User & Comment
                                $user_like_count = get_user_option("_comment_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                update_user_option($user_id, "_comment_like_count", ++$user_like_count);
                                if ($post_users) {
                                    update_comment_meta($post_id, "_user_comment_liked", $post_users);
                                }
                            } else {
                                // Update User & Post
                                $user_like_count = get_user_option("_user_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                update_user_option($user_id, "_user_like_count", ++$user_like_count);
                                if ($post_users) {
                                    update_post_meta($post_id, "_user_liked", $post_users);
                                }
                            }
                        } else { // user is anonymous
                            $user_ip    = $this->toocheke_get_ip();
                            $post_users = $this->toocheke_post_ip_likes($user_ip, $post_id, $is_comment);
                            // Update Post
                            if ($post_users) {
                                if ($is_comment == 1) {
                                    update_comment_meta($post_id, "_user_comment_IP", $post_users);
                                } else {
                                    update_post_meta($post_id, "_user_IP", $post_users);
                                }
                            }
                        }
                        $like_count         = ++$count;
                        $response['status'] = "liked";
                        $response['icon']   = $this->toocheke_get_liked_icon();
                    } else {                   // Unlike the post
                        if (is_user_logged_in()) { // user is logged in
                            $user_id    = get_current_user_id();
                            $post_users = $this->toocheke_post_user_likes($user_id, $post_id, $is_comment);
                            // Update User
                            if ($is_comment == 1) {
                                $user_like_count = get_user_option("_comment_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                if ($user_like_count > 0) {
                                    update_user_option($user_id, "_comment_like_count", --$user_like_count);
                                }
                            } else {
                                $user_like_count = get_user_option("_user_like_count", $user_id);
                                $user_like_count = (isset($user_like_count) && is_numeric($user_like_count)) ? $user_like_count : 0;
                                if ($user_like_count > 0) {
                                    update_user_option($user_id, '_user_like_count', --$user_like_count);
                                }
                            }
                            // Update Post
                            if ($post_users) {
                                $uid_key = array_search($user_id, $post_users);
                                unset($post_users[$uid_key]);
                                if ($is_comment == 1) {
                                    update_comment_meta($post_id, "_user_comment_liked", $post_users);
                                } else {
                                    update_post_meta($post_id, "_user_liked", $post_users);
                                }
                            }
                        } else { // user is anonymous
                            $user_ip    = $this->toocheke_get_ip();
                            $post_users = $this->toocheke_post_ip_likes($user_ip, $post_id, $is_comment);
                            // Update Post
                            if ($post_users) {
                                $uip_key = array_search($user_ip, $post_users);
                                unset($post_users[$uip_key]);
                                if ($is_comment == 1) {
                                    update_comment_meta($post_id, "_user_comment_IP", $post_users);
                                } else {
                                    update_post_meta($post_id, "_user_IP", $post_users);
                                }
                            }
                        }
                        $like_count         = ($count > 0) ? --$count : 0; // Prevent negative number
                        $response['status'] = "unliked";
                        $response['icon']   = $this->toocheke_get_unliked_icon();
                    }
                    if ($is_comment == 1) {
                        update_comment_meta($post_id, "_comment_like_count", $like_count);
                        update_comment_meta($post_id, "_comment_like_modified", date('Y-m-d H:i:s'));
                    } else {
                        update_post_meta($post_id, "_post_like_count", $like_count);
                        update_post_meta($post_id, "_post_like_modified", date('Y-m-d H:i:s'));
                    }
                    $response['count']   = $this->toocheke_get_like_count($like_count);
                    $response['testing'] = $is_comment;
                    if ($disabled == true) {
                        if ($is_comment == 1) {
                            wp_redirect(get_permalink(get_the_ID()));
                            exit();
                        } else {
                            wp_redirect(get_permalink($post_id));
                            exit();
                        }
                    } else {
                        wp_send_json($response);
                    }
                }
            }

            /**
             * Check if the post is already liked
             */
            public function toocheke_check_if_liked($post_id, $is_comment)
            {
                $post_users = null;
                $user_id    = null;
                if (is_user_logged_in()) { // user is logged in
                    $user_id         = get_current_user_id();
                    $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_liked") : get_post_meta($post_id, "_user_liked");
                    if (count($post_meta_users) != 0) {
                        $post_users = $post_meta_users[0];
                    }
                } else { // user is anonymous
                    $user_id         = $this->toocheke_get_ip();
                    $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_IP") : get_post_meta($post_id, "_user_IP");
                    if (count($post_meta_users) != 0) { // meta exists, set up values
                        $post_users = $post_meta_users[0];
                    }
                }
                if (is_array($post_users) && in_array($user_id, $post_users)) {
                    return true;
                } else {
                    return false;
                }
            } // toocheke_check_if_liked()

            /**
             * Get like button
             */
            public function toocheke_get_like_button($post_id, $is_comment = null)
            {
                $is_comment = (null == $is_comment) ? 0 : 1;
                $output     = '';
                $nonce      = wp_create_nonce('toocheke-likes-nonce'); // Security
                if ($is_comment == 1) {
                    $post_id_class = esc_attr(' toocheke-likes-comment-button-' . $post_id);
                    $comment_class = esc_attr(' toocheke-likes-comment');
                    $like_count    = get_comment_meta($post_id, "_comment_like_count", true);
                    $like_count    = (isset($like_count) && is_numeric($like_count)) ? $like_count : 0;
                } else {
                    $post_id_class = esc_attr(' toocheke-likes-button-' . $post_id);
                    $comment_class = esc_attr('');
                    $like_count    = get_post_meta($post_id, "_post_like_count", true);
                    $like_count    = (isset($like_count) && is_numeric($like_count)) ? $like_count : 0;
                }
                $count      = $this->toocheke_get_like_count($like_count);
                $icon_empty = $this->toocheke_get_unliked_icon();
                $icon_full  = $this->toocheke_get_liked_icon();
                // Loader
                $loader = '<span id="toocheke-likes-loader"></span>';
                // Liked/Unliked Variables
                if ($this->toocheke_check_if_liked($post_id, $is_comment)) {
                    $class = esc_attr(' liked');
                    $title = __('Unlike', 'toocheke-companion');
                    $icon  = $icon_full;
                } else {
                    $class = '';
                    $title = __('Like', 'toocheke-companion');
                    $icon  = $icon_empty;
                }
                $output = '<span class="toocheke-likes-wrapper"><a href="' . admin_url('admin-ajax.php?action=toocheke_process_like' . '&post_id=' . $post_id . '&nonce=' . $nonce . '&is_comment=' . $is_comment . '&disabled=true') . '" class="toocheke-likes-button' . $post_id_class . $class . $comment_class . '" data-nonce="' . $nonce . '" data-post-id="' . $post_id . '" data-iscomment="' . $is_comment . '" title="' . $title . '">' . $icon . $count . '</a>' . $loader . '</span>';
                return $output;
            } // toocheke_get_like_button()

            /**
             * Add button shortcode
             */

            public function toocheke_like_short_code()
            {
                return $this->toocheke_get_like_button(get_the_ID(), 0);
            } // shortcode()

            /**
             * Gets post meta user likes (user id array),
             * then adds new user id to retrieved array
             */
            public function toocheke_post_user_likes($user_id, $post_id, $is_comment)
            {
                $post_users      = '';
                $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_liked") : get_post_meta($post_id, "_user_liked");
                if (count($post_meta_users) != 0) {
                    $post_users = $post_meta_users[0];
                }
                if (! is_array($post_users)) {
                    $post_users = [];
                }
                if (! in_array($user_id, $post_users)) {
                    $post_users['user-' . $user_id] = $user_id;
                }
                return $post_users;
            } // toocheke_post_user_likes()

            /**
             * Gets post meta ip likes (ip array),
             * then adds new ip to retrieved array
             */
            public function toocheke_post_ip_likes($user_ip, $post_id, $is_comment)
            {
                $post_users      = '';
                $post_meta_users = ($is_comment == 1) ? get_comment_meta($post_id, "_user_comment_IP") : get_post_meta($post_id, "_user_IP");
                // Retrieve post information
                if (count($post_meta_users) != 0) {
                    $post_users = $post_meta_users[0];
                }
                if (! is_array($post_users)) {
                    $post_users = [];
                }
                if (! in_array($user_ip, $post_users)) {
                    $post_users['ip-' . $user_ip] = $user_ip;
                }
                return $post_users;
            } // toocheke_post_ip_likes()

            /**
             * Utility to retrieve IP address
             */
            public function toocheke_get_ip()
            {
                if (isset($_SERVER['HTTP_CLIENT_IP']) && ! empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else {
                    $ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
                }
                $ip = filter_var($ip, FILTER_VALIDATE_IP);
                $ip = ($ip === false) ? '0.0.0.0' : $ip;
                return $ip;
            } // toocheke_get_ip()

            /**
             * Utility returns the button icon for "like" action
             */
            public function toocheke_get_liked_icon()
            {
                /* If already using Font Awesome with your theme, replace svg with: <i class="fa fa-heart"></i> */
                $icon = '<span class="toocheke-likes-icon"><svg role="img" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0" y="0" viewBox="0 0 128 128" enable-background="new 0 0 128 128" xml:space="preserve"><path id="heart-full" d="M124 20.4C111.5-7 73.7-4.8 64 19 54.3-4.9 16.5-7 4 20.4c-14.7 32.3 19.4 63 60 107.1C104.6 83.4 138.7 52.7 124 20.4z"/>&#9829;</svg></span>';
                return $icon;
            } // toocheke_get_liked_icon()

            /**
             * Utility returns the button icon for "unlike" action
             */
            public function toocheke_get_unliked_icon()
            {
                /* If already using Font Awesome with your theme, replace svg with: <i class="fa fa-heart-o"></i> */
                $icon = '<span class="toocheke-likes-icon"><svg role="img" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0" y="0" viewBox="0 0 128 128" enable-background="new 0 0 128 128" xml:space="preserve"><path id="heart" d="M64 127.5C17.1 79.9 3.9 62.3 1 44.4c-3.5-22 12.2-43.9 36.7-43.9 10.5 0 20 4.2 26.4 11.2 6.3-7 15.9-11.2 26.4-11.2 24.3 0 40.2 21.8 36.7 43.9C124.2 62 111.9 78.9 64 127.5zM37.6 13.4c-9.9 0-18.2 5.2-22.3 13.8C5 49.5 28.4 72 64 109.2c35.7-37.3 59-59.8 48.6-82 -4.1-8.7-12.4-13.8-22.3-13.8 -15.9 0-22.7 13-26.4 19.2C60.6 26.8 54.4 13.4 37.6 13.4z"/>&#9829;</svg></span>';
                return $icon;
            } // toocheke_get_unliked_icon()

            /**
             * Formatting like count
             * appending "K" if one thousand or greater,
             * "M" if one million or greater,
             * and "B" if one billion or greater (unlikely).
             * $precision = how many decimal points to display (1.25K)
             */
            public function toocheke_format_count($number)
            {
                $precision = 2;
                if ($number >= 1000 && $number < 1000000) {
                    $formatted = number_format($number / 1000, $precision) . 'K';
                } else if ($number >= 1000000 && $number < 1000000000) {
                    $formatted = number_format($number / 1000000, $precision) . 'M';
                } else if ($number >= 1000000000) {
                    $formatted = number_format($number / 1000000000, $precision) . 'B';
                } else {
                    $formatted = $number; // Number is less than 1000
                }
                $formatted = str_replace('.00', '', $formatted);
                return $formatted;
            } // toocheke_format_count()

            /**
             * Get like count
             */
            public function toocheke_get_like_count($like_count)
            {
                $like_text = __('Like', 'toocheke-companion');
                if (is_numeric($like_count) && $like_count > 0) {
                    $number = $this->toocheke_format_count($like_count);
                } else {
                    $number = $like_text;
                }
                $count = '<span class="toocheke-likes-count">' . $number . '</span>';
                return $count;
            } // toocheke_get_like_count()

            // User Profile List
            public function toocheke_show_user_likes($user)
            { ?>
        <table class="form-table">
            <tr>
                <th><label for="user_likes"><?php _e('You Like:', 'toocheke-companion'); ?></label></th>
                <td>
                    <?php
                    $types = get_post_types(['public' => true]);
                    $args  = [
                        'numberposts' => -1,
                        'post_type'   => $types,
                        'meta_query'  => [
                            [
                                'key'     => '_user_liked',
                                'value'   => $user->ID,
                                'compare' => 'LIKE',
                            ],
                        ]
                    ];
                    $sep        = '';
                    $like_query = new WP_Query($args);
                    if ($like_query->have_posts()): ?>
                        <p>
                            <?php while ($like_query->have_posts()): $like_query->the_post();
                                echo $sep; ?><a href="<?php the_permalink(); ?>"
                                    title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
                            <?php
                                $sep = ' &middot; ';
                            endwhile;
                            ?>
                        </p>
                    <?php else: ?>
                        <p><?php _e('You do not like anything yet.', 'toocheke-companion'); ?></p>
                    <?php
                    endif;
                    wp_reset_postdata();
                    ?>
                </td>
            </tr>
        </table>
    <?php } // toocheke_show_user_likes()

}
