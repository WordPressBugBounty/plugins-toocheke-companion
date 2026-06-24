<?php
/**
 * Front-end behavior that doesn't belong to a more specific file: enqueuing
 * scripts/styles (including the manga reader's Swiper/fullscreen scripts),
 * template selection for single comic/manga views, comment handling, search,
 * view counts, hovertext on comic images, and related content filters.
 *
 * Used by {@see Toocheke_Companion_Comic_Features} in toocheke-companion.php,
 * which `use`s this trait alongside the others in /inc.
 */

if (!defined('ABSPATH')) { exit; }

trait Toocheke_Companion_Frontend_Display
{
            /* Social Sharing Functions */

            public function toocheke_add_sharing_icons()
            {
                $allowed_tags = [
                    'a'    => [
                        'title' => [],
                        'href'  => [],
                    ],
                    'i'    => [
                        'class' => [],
                    ],
                    'img'  => [
                        'class' => [],
                        'src'   => [],
                    ],
                    'svg'  => [
                        'xmlns'       => [],
                        'fill'        => [],
                        'viewbox'     => [],
                        'role'        => [],
                        'aria-hidden' => [],
                        'focusable'   => [],
                    ],
                    'path' => [
                        'd'    => [],
                        'fill' => [],
                    ],
                ];
                //custom button icons
                $display_default_button = get_option('toocheke-comics-navigation') && 1 == get_option('toocheke-comics-navigation');

                $facebook_image_button_url = get_option('toocheke-facebook-button');
                $facebook_button           = $display_default_button ? '<i class="fab fa-lg fa-facebook-f" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($facebook_image_button_url) . '" />';
                $twitter_image_button_url  = get_option('toocheke-twitter-button');
                $twitter_button            = $display_default_button ? '<i class="fab fa-lg fa-x-twitter" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($twitter_image_button_url) . '" />';
                $tumblr_image_button_url   = get_option('toocheke-tumblr-button');
                $tumblr_button             = $display_default_button ? '<i class="fab fa-lg fa-tumblr" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($tumblr_image_button_url) . '" />';
                $reddit_image_button_url   = get_option('toocheke-reddit-button');
                $reddit_button             = $display_default_button ? '<i class="fab fa-lg fa-reddit-alien" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($reddit_image_button_url) . '" />';
                $copy_image_button_url     = get_option('toocheke-copy-button');
                $copy_button               = $display_default_button ? '<i class="fas fa-lg fa-copy" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($copy_image_button_url) . '" />';
                $threads_image_button_url  = get_option('toocheke-threads-button');
                $threads_button            = $display_default_button ? '<i class="fab fa-lg fa-threads" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($threads_image_button_url) . '" />';
                $bluesky_image_button_url  = get_option('toocheke-bluesky-button');
                $bluesky_button            = $display_default_button ? '<i class="fab fa-lg fa-bluesky" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($bluesky_image_button_url) . '" />';
                $whatsapp_image_button_url = get_option('toocheke-whatsapp-button');
                $whatsapp_button           = $display_default_button ? '<i class="fab fa-lg fa-whatsapp" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($whatsapp_image_button_url) . '" />';
                $linkedin_image_button_url = get_option('toocheke-linkedin-button');
                $linkedin_button           = $display_default_button ? '<i class="fab fa-lg fa-linkedin" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($linkedin_image_button_url) . '" />';

                $html         = "";
                $comic_url    = home_url($_SERVER['REQUEST_URI']);
                $social_url   = urlencode(home_url($_SERVER['REQUEST_URI']));
                $social_title = urlencode(html_entity_decode(get_the_title(), ENT_COMPAT, 'UTF-8'));
                $social_media = urlencode(get_the_post_thumbnail_url(get_the_ID(), 'full'));
                $facebook_url = 'https://www.facebook.com/sharer?u=' . $social_url . '&amp;t=' . $social_title;
                $twitter_url  = 'https://twitter.com/intent/tweet?text=' . $social_title . '&amp;url=' . $social_url;
                $tumblr_url   = 'https://tumblr.com/widgets/share/tool?canonicalUrl=' . $social_url;
                $reddit_url   = 'https://www.reddit.com/submit?url=' . $social_url . '&amp;title=' . $social_title;
                $threads_url  = 'https://threads.net/intent/post?text=' . $social_title . ' ' . $social_url;
                $bluesky_url  = 'https://bsky.app/intent/compose?text=' . $social_title . ' ' . $social_url;
                $whatsapp_url = 'https://api.whatsapp.com/send?text=' . $social_title . ' ' . $social_url;
                $linkedin_url = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $social_url;

                if (get_option("toocheke-social-share-facebook") == 1) {
                    $html = $html . "<a href='" . esc_url($facebook_url) . "' title='Share on Facebook' target='_blank'>" . wp_kses($facebook_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-twitter") == 1) {
                    $html = $html . "<a href='" . esc_url($twitter_url) . "' title='Share on Twitter' target='_blank'>" . wp_kses($twitter_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-tumblr") == 1) {
                    $html = $html . "<a href='" . esc_url($tumblr_url) . "' title='Share on Tumblr' target='_blank'>" . wp_kses($tumblr_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-reddit") == 1) {
                    $html = $html . "<a href='" . esc_url($reddit_url) . "' title='Share on Reddit' target='_blank'>" . wp_kses($reddit_button, $allowed_tags) . "</a>";
                }

                if (get_option("toocheke-social-share-threads") == 1) {
                    $html = $html . "<a href='" . esc_url($threads_url) . "' title='Share on Threads' target='_blank'>" . wp_kses($threads_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-bluesky") == 1) {
                    $html = $html . "<a href='" . esc_url($bluesky_url) . "' title='Share on Bluesky' target='_blank'>" . wp_kses($bluesky_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-whatsapp") == 1) {
                    $html = $html . "<a href='" . esc_url($whatsapp_url) . "' title='Share on WhatsApp' target='_blank'>" . wp_kses($whatsapp_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-linkedin") == 1) {
                    $html = $html . "<a href='" . esc_url($linkedin_url) . "' title='Share on LinkedIn' target='_blank'>" . wp_kses($linkedin_button, $allowed_tags) . "</a>";
                }
                if (get_option("toocheke-social-share-copy") == 1) {
                    $html = $html . "<a id='copy-link' data-url='" . esc_url($comic_url) . "' href='javascript:;' title='Copy link'>" . wp_kses($copy_button, $allowed_tags) . "</a>";
                }

                echo $html;
            }

            /* Support Link Functions */
            public function toocheke_add_support_icons()
            {
                $allowed_tags = [
                    'a'    => [
                        'title' => [],
                        'href'  => [],
                    ],
                    'i'    => [
                        'class' => [],
                    ],
                    'img'  => [
                        'class' => [],
                        'src'   => [],
                    ],
                    'svg'  => [
                        'xmlns'       => [],
                        'fill'        => [],
                        'viewbox'     => [],
                        'role'        => [],
                        'aria-hidden' => [],
                        'focusable'   => [],
                    ],
                    'path' => [
                        'd'    => [],
                        'fill' => [],
                    ],
                ];
                //custom button icons
                $display_default_button = get_option('toocheke-comics-navigation') && 1 == get_option('toocheke-comics-navigation');

                $buymeacoffee_image_button_url = get_option('toocheke-buymeacoffee-button');
                $buymeacoffee_button           = $display_default_button ? '<svg fill="#000000" width="800px" height="800px" viewBox="0 0 24 24" role="img" xmlns="http://www.w3.org/2000/svg"><path d="m20.216 6.415-.132-.666c-.119-.598-.388-1.163-1.001-1.379-.197-.069-.42-.098-.57-.241-.152-.143-.196-.366-.231-.572-.065-.378-.125-.756-.192-1.133-.057-.325-.102-.69-.25-.987-.195-.4-.597-.634-.996-.788a5.723 5.723 0 0 0-.626-.194c-1-.263-2.05-.36-3.077-.416a25.834 25.834 0 0 0-3.7.062c-.915.083-1.88.184-2.75.5-.318.116-.646.256-.888.501-.297.302-.393.77-.177 1.146.154.267.415.456.692.58.36.162.737.284 1.123.366 1.075.238 2.189.331 3.287.37 1.218.05 2.437.01 3.65-.118.299-.033.598-.073.896-.119.352-.054.578-.513.474-.834-.124-.383-.457-.531-.834-.473-.466.074-.96.108-1.382.146-1.177.08-2.358.082-3.536.006a22.228 22.228 0 0 1-1.157-.107c-.086-.01-.18-.025-.258-.036-.243-.036-.484-.08-.724-.13-.111-.027-.111-.185 0-.212h.005c.277-.06.557-.108.838-.147h.002c.131-.009.263-.032.394-.048a25.076 25.076 0 0 1 3.426-.12c.674.019 1.347.067 2.017.144l.228.031c.267.04.533.088.798.145.392.085.895.113 1.07.542.055.137.08.288.111.431l.319 1.484a.237.237 0 0 1-.199.284h-.003c-.037.006-.075.01-.112.015a36.704 36.704 0 0 1-4.743.295 37.059 37.059 0 0 1-4.699-.304c-.14-.017-.293-.042-.417-.06-.326-.048-.649-.108-.973-.161-.393-.065-.768-.032-1.123.161-.29.16-.527.404-.675.701-.154.316-.199.66-.267 1-.069.34-.176.707-.135 1.056.087.753.613 1.365 1.37 1.502a39.69 39.69 0 0 0 11.343.376.483.483 0 0 1 .535.53l-.071.697-1.018 9.907c-.041.41-.047.832-.125 1.237-.122.637-.553 1.028-1.182 1.171-.577.131-1.165.2-1.756.205-.656.004-1.31-.025-1.966-.022-.699.004-1.556-.06-2.095-.58-.475-.458-.54-1.174-.605-1.793l-.731-7.013-.322-3.094c-.037-.351-.286-.695-.678-.678-.336.015-.718.3-.678.679l.228 2.185.949 9.112c.147 1.344 1.174 2.068 2.446 2.272.742.12 1.503.144 2.257.156.966.016 1.942.053 2.892-.122 1.408-.258 2.465-1.198 2.616-2.657.34-3.332.683-6.663 1.024-9.995l.215-2.087a.484.484 0 0 1 .39-.426c.402-.078.787-.212 1.074-.518.455-.488.546-1.124.385-1.766zm-1.478.772c-.145.137-.363.201-.578.233-2.416.359-4.866.54-7.308.46-1.748-.06-3.477-.254-5.207-.498-.17-.024-.353-.055-.47-.18-.22-.236-.111-.71-.054-.995.052-.26.152-.609.463-.646.484-.057 1.046.148 1.526.22.577.088 1.156.159 1.737.212 2.48.226 5.002.19 7.472-.14.45-.06.899-.13 1.345-.21.399-.072.84-.206 1.08.206.166.281.188.657.162.974a.544.544 0 0 1-.169.364zm-6.159 3.9c-.862.37-1.84.788-3.109.788a5.884 5.884 0 0 1-1.569-.217l.877 9.004c.065.78.717 1.38 1.5 1.38 0 0 1.243.065 1.658.065.447 0 1.786-.065 1.786-.065.783 0 1.434-.6 1.499-1.38l.94-9.95a3.996 3.996 0 0 0-1.322-.238c-.826 0-1.491.284-2.26.613z"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($buymeacoffee_image_button_url) . '" />';
                $gumroad_image_button_url      = get_option('toocheke-gumroad-button');
                $gumroad_button                = $display_default_button ? '<svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><path d="m48 24c0 13.255-10.745 24-24 24s-24-10.745-24-24 10.745-24 24-24 24 10.745 24 24zm-37.25-0.5577c0 7.0142 4.1354 12.653 11.166 12.653 7.0304 0 8.8223-5.6388 9.2358-8.5269v7.9768h5.7105v-12.653h-13.016v2.4754h6.2033c-0.6893 3.0258-2.3435 6.0515-6.2033 6.0515-4.2733 0-7.0303-3.7134-7.0303-8.252 0-4.5385 2.757-8.2519 7.0303-8.2519 3.9977 0 5.6519 2.8882 5.7897 4.8136h6.4788c-0.1378-3.4383-3.1705-9.4896-12.131-9.4896-8.6844 0-13.234 5.9139-13.234 13.203z" clip-rule="evenodd" fill="#fff" fill-rule="evenodd"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($gumroad_image_button_url) . '" />';
                $indiegogo_image_button_url    = get_option('toocheke-indiegogo-button');
                $indiegogo_button              = $display_default_button ? '<svg xmlns="http://www.w3.org/2000/svg" width="321.547" height="186.016" viewBox="0 0 321.547 186.016" xml:space="preserve"><g fill="#FFF"><path d="M242.411 0c-67.313 0-79.365 44.115-79.365 92.779 0 48.438 12.053 92.553 79.365 92.553 67.082 0 79.135-44.115 79.135-92.553C321.546 44.342 309.95 0 242.411 0zm0 142.809c-26.607 0-30.246-18.42-30.246-48.209 0-29.563 3.639-47.983 30.246-47.983 26.377 0 30.016 18.42 30.016 47.983 0 29.789-3.639 48.209-30.016 48.209zM70.267 113.473h31.154v3.186c0 20.012.682 28.881-23.195 28.881-22.285 0-29.79-10.234-29.79-50.939 0-42.524 12.734-47.526 31.837-47.526 14.1 0 24.104 3.639 32.52 8.414l25.924-40.25C125.069 6.369 105.06.684 82.547.684 20.012.684 0 30.701 0 93.463c0 54.805 14.554 92.553 73.905 92.553 10.46 0 19.558-.682 27.287-2.273h44.572V74.135h-70.04l-5.457 39.338z"/></g></svg>' : '<img class="comic-image-nav" src="' . esc_attr($indiegogo_image_button_url) . '" />';
                $kickstarter_image_button_url  = get_option('toocheke-kickstarter-button');
                $kickstarter_button            = $display_default_button ? '<i class="fab fa-lg fa-kickstarter" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($kickstarter_image_button_url) . '" />';
                $kofi_image_button_url         = get_option('toocheke-kofi-button');
                $kofi_button                   = $display_default_button ? '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M23.881 8.948c-.773-4.085-4.859-4.593-4.859-4.593H.723c-.604 0-.679.798-.679.798s-.082 7.324-.022 11.822c.164 2.424 2.586 2.672 2.586 2.672s8.267-.023 11.966-.049c2.438-.426 2.683-2.566 2.658-3.734 4.352.24 7.422-2.831 6.649-6.916zm-11.062 3.511c-1.246 1.453-4.011 3.976-4.011 3.976s-.121.119-.31.023c-.076-.057-.108-.09-.108-.09-.443-.441-3.368-3.049-4.034-3.954-.709-.965-1.041-2.7-.091-3.71.951-1.01 3.005-1.086 4.363.407 0 0 1.565-1.782 3.468-.963 1.904.82 1.832 3.011.723 4.311zm6.173.478c-.928.116-1.682.028-1.682.028V7.284h1.77s1.971.551 1.971 2.638c0 1.913-.985 2.667-2.059 3.015z"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($kofi_image_button_url) . '" />';
                $liberapay_image_button_url    = get_option('toocheke-liberapay-button');
                $liberapay_button              = $display_default_button ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><g fill="#1a171b"><path d="M25.91 63.04c-3.571 0-6.373-.466-8.41-1.396-2.037-.93-3.495-2.199-4.375-3.809-.88-1.609-1.308-3.457-1.282-5.544.025-2.086.313-4.311.868-6.675l9.579-40.05 11.69-1.81-10.484 43.44a13.563 13.563 0 0 0-.339 2.489c-.026.754.113 1.421.415 1.999.302.579.817 1.044 1.546 1.395.729.353 1.747.579 3.055.679l-2.263 9.278M68.15 38.08c0 3.671-.604 7.03-1.811 10.07-1.207 3.043-2.879 5.669-5.01 7.881-2.138 2.213-4.702 3.935-7.693 5.167-2.992 1.231-6.248 1.848-9.767 1.848-1.71 0-3.42-.151-5.129-.453l-3.394 13.651H24.184l12.52-52.19c2.01-.603 4.311-1.143 6.901-1.622 2.589-.477 5.393-.716 8.41-.716 2.815 0 5.242.428 7.278 1.282 2.037.855 3.708 2.024 5.02 3.507 1.307 1.484 2.274 3.219 2.904 5.205.627 1.987.942 4.11.942 6.373M40.781 53.544c.854.202 1.91.302 3.167.302 1.961 0 3.746-.364 5.355-1.094a11.799 11.799 0 0 0 4.111-3.055c1.131-1.307 2.01-2.877 2.64-4.714.628-1.835.943-3.858.943-6.071 0-2.161-.479-3.998-1.433-5.506-.956-1.508-2.615-2.263-4.978-2.263-1.61 0-3.118.151-4.525.453l-5.28 21.948"/></g></svg>' : '<img class="comic-image-nav" src="' . esc_attr($liberapay_image_button_url) . '" />';
                $patreon_image_button_url      = get_option('toocheke-patreon-button');
                $patreon_button                = $display_default_button ? '<i class="fab fa-lg fa-patreon" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($patreon_image_button_url) . '" />';
                $paypal_image_button_url       = get_option('toocheke-paypal-button');
                $paypal_button                 = $display_default_button ? '<i class="fab fa-lg fa-paypal" aria-hidden="true"></i>' : '<img class="comic-image-nav" src="' . esc_attr($paypal_image_button_url) . '" />';
                $substack_image_button_url     = get_option('toocheke-substack-button');
                $substack_button               = $display_default_button ? '<svg xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 448 511.471"><path fill="#FF681A" d="M0 0h448v62.804H0V0zm0 229.083h448v282.388L223.954 385.808 0 511.471V229.083zm0-114.542h448v62.804H0v-62.804z"/></svg>' : '<img class="comic-image-nav" src="' . esc_attr($substack_image_button_url) . '" />';
                $tipeee_image_button_url       = get_option('toocheke-tipeee-button');
                $tipeee_button                 = $display_default_button ? '<svg xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" version="1.1" id="Layer_1" x="0px" y="0px" width="888.00201" height="335.15942" viewBox="0 0 888.00201 335.15942" enable-background="new 0 0 1009 472" xml:space="preserve" sodipodi:docname="Tipeee_logo_.svg" inkscape:version="0.92.1 r15371"><metadata id="metadata41"><rdf:RDF><cc:Work rdf:about=""><dc:format></dc:format><dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage" /><dc:title></dc:title></cc:Work></rdf:RDF></metadata><defs id="defs39" /><sodipodi:namedview pagecolor="#ffffff" bordercolor="#666666" borderopacity="1" objecttolerance="10" gridtolerance="10" guidetolerance="10" inkscape:pageopacity="0" inkscape:pageshadow="2" inkscape:window-width="1920" inkscape:window-height="1017" id="namedview37" showgrid="false" fit-margin-top="0" fit-margin-left="0" fit-margin-right="0" fit-margin-bottom="0" inkscape:zoom="0.70564916" inkscape:cx="301.30419" inkscape:cy="163.71331" inkscape:window-x="-8" inkscape:window-y="-8" inkscape:window-maximized="1" inkscape:current-layer="Layer_1" /><path d="m 888.002,134.4 c -0.808,1.266 -0.516,2.772 -0.922,4.146 -2.004,6.776 -5.498,12.746 -9.693,18.348 -7.275,9.714 -15.759,18.295 -25.001,26.111 -24.586,20.791 -51.223,38.508 -80.07,52.844 -17.634,8.763 -35.871,16.008 -54.891,21.184 -9.907,2.696 -19.967,4.514 -30.292,3.996 -11.321,-0.566 -21.675,-3.788 -30.269,-11.57 -2.327,-2.107 -4.334,-4.483 -6.127,-7.044 -1.036,-1.48 -0.741,-1.7 -2.768,-0.567 -8.1,4.527 -16.448,8.52 -25.168,11.724 -9.337,3.431 -18.914,5.863 -28.812,6.909 -10.236,1.081 -20.275,0.364 -29.85,-3.799 -7.308,-3.178 -13.14,-8.147 -17.56,-14.762 -1.194,-1.787 -1.045,-1.589 -2.743,-0.626 -9.887,5.608 -20.108,10.485 -30.848,14.252 -8.584,3.011 -17.401,4.965 -26.494,4.968 -18.408,0.006 -32.272,-7.824 -40.765,-24.471 -0.252,-0.494 -0.524,-0.979 -0.797,-1.464 -0.035,-0.063 -0.126,-0.096 -0.227,-0.167 -3.169,1.447 -6.352,2.919 -9.551,4.354 -2.694,1.208 -5.523,1.681 -8.424,1.064 -5.737,-1.218 -9.247,-7.016 -7.993,-13.085 0.727,-3.519 2.583,-6.049 6.032,-7.477 4.499,-1.861 8.899,-3.957 13.24,-6.176 1.009,-0.517 1.357,-1.134 1.269,-2.24 -0.185,-2.308 -0.334,-4.624 -0.354,-6.938 -0.14,-15.692 2.429,-31.027 6.25,-46.181 3.527,-13.989 8.275,-27.534 15.057,-40.311 7.707,-14.522 17.6,-27.226 30.897,-37.098 7.085,-5.26 14.812,-9.122 23.49,-10.944 6.183,-1.297 12.361,-1.54 18.499,0.239 7.429,2.152 12.691,6.897 16.088,13.749 3.65,7.36 4.784,15.163 3.938,23.311 -1.159,11.17 -5.228,21.383 -10.352,31.208 -9.628,18.463 -21.434,35.41 -35.785,50.538 -12.108,12.764 -25.558,23.912 -40.34,33.451 -0.403,0.261 -0.795,0.54 -1.209,0.781 -0.659,0.385 -0.789,0.825 -0.486,1.582 3.493,8.741 9.256,13.74 19.354,14.239 7.398,0.366 14.451,-1.405 21.318,-3.883 10.852,-3.915 21.068,-9.189 31.074,-14.896 1.266,-0.722 1.572,-1.539 1.398,-2.886 -0.733,-5.651 -0.748,-11.34 -0.367,-17.003 0.757,-11.266 2.652,-22.363 5.617,-33.262 5.103,-18.759 12.393,-36.624 22.393,-53.313 7.249,-12.099 16.254,-22.76 27.271,-31.634 8.438,-6.796 17.99,-11.171 28.688,-13.079 15.613,-2.785 29.301,7.075 33.52,20.471 2.778,8.822 2.425,17.651 0.221,26.493 -1.672,6.711 -4.599,12.943 -7.619,19.115 -8.989,18.372 -20.758,34.861 -34.202,50.205 -14.088,16.078 -29.968,30.146 -47.496,42.365 -0.328,0.229 -0.642,0.481 -0.985,0.684 -0.728,0.429 -0.691,0.925 -0.282,1.589 1.535,2.494 3.463,4.597 6.011,6.07 3.821,2.211 7.958,3.13 12.366,3.274 8.582,0.282 16.854,-1.33 25.032,-3.681 11.449,-3.291 21.973,-8.638 32.274,-14.487 1.053,-0.599 1.468,-1.3 1.248,-2.507 -0.679,-3.711 -0.709,-7.49 -0.717,-11.229 -0.01,-4.704 0.242,-9.42 0.81,-14.107 1.678,-13.852 4.782,-27.371 9.423,-40.528 5.765,-16.34 12.854,-32.044 22.735,-46.358 8.224,-11.914 18.105,-22.143 30.725,-29.496 6.117,-3.564 12.546,-6.339 19.569,-7.542 8.423,-1.444 16.291,-0.253 23.01,5.396 5.617,4.722 9.184,10.806 10.988,17.9 2.037,8.002 1.555,16.013 -0.173,23.987 -2.271,10.486 -6.552,20.185 -11.762,29.505 -8.04,14.383 -17.938,27.412 -28.972,39.604 -14.482,16.001 -30.328,30.486 -47.944,42.994 -0.39,0.277 -0.813,0.52 -1.054,1.122 2.426,4.136 6.092,6.755 10.662,8.251 4.275,1.4 8.682,1.249 13.071,1.086 9.665,-0.358 18.927,-2.859 28.127,-5.569 12.521,-3.688 24.511,-8.746 36.249,-14.41 23.827,-11.5 46.328,-25.189 67.49,-41.068 11.222,-8.42 21.671,-17.698 30.984,-28.22 3.727,-4.209 7.033,-8.703 9.54,-13.755 0.968,-1.95 1.577,-3.993 1.798,-6.173 0.507,-4.99 4.329,-8.887 9.354,-9.473 2.308,-0.269 4.615,-0.238 6.836,0.719 2.736,1.179 4.492,3.196 5.445,5.973 0.003,2.558 0.003,5.118 0.003,7.678 z m -224.655,70.47 c 1.066,-0.177 1.524,-0.771 2.073,-1.201 10.659,-8.334 20.6,-17.46 29.987,-27.198 13.38,-13.879 25.09,-29.003 34.116,-46.089 4.32,-8.177 7.582,-16.755 8.01,-26.128 0.153,-3.341 -0.108,-6.702 -1.729,-9.784 -1.312,-2.496 -3.34,-3.713 -6.161,-3.837 -3.565,-0.157 -6.763,1.021 -9.878,2.512 -6.728,3.222 -12.306,7.983 -17.504,13.234 -9.058,9.149 -15.565,20.026 -21.168,31.491 -7.261,14.859 -12.38,30.452 -15.631,46.657 -1.335,6.659 -2.155,13.382 -2.115,20.343 z m -103.604,-0.794 c 0.846,-0.074 1.177,-0.454 1.547,-0.736 9.423,-7.182 18.313,-14.962 26.626,-23.416 14.95,-15.205 27.562,-32.133 37.733,-50.872 2.935,-5.407 5.771,-10.866 7.667,-16.756 1.459,-4.534 2.299,-9.14 1.759,-13.917 -0.668,-5.908 -4.334,-8.783 -10.223,-8.041 -0.315,0.04 -0.623,0.135 -0.937,0.194 -3.896,0.732 -7.479,2.273 -10.863,4.287 -9.443,5.621 -16.862,13.413 -23.286,22.175 -5.209,7.104 -9.545,14.763 -13.268,22.749 -7.192,15.429 -12.214,31.559 -15.258,48.295 -0.956,5.257 -1.412,10.588 -1.497,16.038 z m -98.412,-5.608 c 0.758,0.03 1.063,-0.241 1.381,-0.464 10.181,-7.147 19.467,-15.322 28.06,-24.307 11.567,-12.095 21.104,-25.682 29.388,-40.165 4.615,-8.07 8.449,-16.507 10.43,-25.661 0.764,-3.533 1.058,-7.11 0.282,-10.706 -1.098,-5.089 -3.827,-7.27 -8.972,-6.95 -3.826,0.238 -7.3,1.73 -10.606,3.521 -10.578,5.729 -18.648,14.223 -25.488,23.904 -4.858,6.875 -8.458,14.487 -11.575,22.3 -5.319,13.333 -9.088,27.106 -11.384,41.271 -0.922,5.678 -1.397,11.412 -1.516,17.257 z" id="path4" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 0,71.04 c 0.153,-0.028 0.394,-0.005 0.445,-0.093 1.374,-2.352 3.553,-2.599 6.001,-2.527 3.917,0.115 7.838,0.051 11.758,0.021 1.689,-0.013 1.82,-0.072 1.739,-1.782 -0.287,-6.073 -0.135,-12.15 -0.219,-18.224 -0.051,-3.679 -0.1,-7.36 -0.023,-11.037 0.103,-4.929 2.468,-7.79 7.172,-9.198 13.766,-4.122 27.585,-8.069 41.186,-12.728 1.127,-0.387 2.308,-0.654 3.485,-0.856 4.409,-0.756 7.554,1.577 8.08,6.022 0.14,1.183 0.055,2.394 0.055,3.592 0.002,13.998 -0.001,27.996 0.005,41.995 0.001,2.195 0.022,2.221 2.276,2.224 9.599,0.01 19.197,0.003 28.796,0.007 0.719,0 1.446,-0.035 2.156,0.058 1.87,0.244 3.178,1.599 3.404,3.479 0.057,0.474 0.036,0.958 0.036,1.438 0.002,13.678 0.008,27.356 -0.003,41.035 -0.003,3.486 -1.474,4.938 -4.956,4.985 -0.48,0.006 -0.96,0.001 -1.44,0.001 -9.119,0 -18.238,-0.008 -27.356,0.004 -3.323,0.004 -2.909,-0.317 -2.91,2.97 -0.011,22.797 -0.008,45.594 -10e-4,68.392 0,1.596 -0.013,3.188 0.337,4.771 0.936,4.224 3.448,6.624 7.705,7.493 3.961,0.81 7.802,0.111 11.63,-0.737 3.669,-0.813 7.221,-2.026 10.671,-3.524 0.293,-0.128 0.58,-0.272 0.877,-0.387 3.363,-1.296 5.284,-0.074 5.427,3.548 0.091,2.316 0.022,4.64 0.022,6.959 0,10.799 0,21.597 0,32.396 0,4.617 -1.91,7.508 -6.217,9.18 -7.152,2.776 -14.447,5.099 -21.913,6.901 -8.99,2.172 -18.082,3.348 -27.323,2.715 -10.777,-0.738 -20.479,-4.16 -28.149,-12.155 -5.874,-6.121 -9.208,-13.571 -11.084,-21.729 -1.078,-4.685 -1.539,-9.441 -1.529,-14.266 0.046,-23.598 0.026,-47.194 0.018,-70.792 -0.002,-6.479 -0.043,-12.957 -0.071,-19.435 -0.01,-2.29 -0.017,-2.298 -2.393,-2.303 -3.839,-0.007 -7.682,-0.084 -11.518,0.029 C 3.848,119.55 1.899,119.099 0.604,116.988 0.498,116.815 0.206,116.755 0,116.642 0,101.44 0,86.24 0,71.04 Z" id="path6" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d84759;fill-rule:evenodd" /><path d="m 188.161,0 c 4.22,0.528 8.272,1.563 12.077,3.564 11.113,5.844 17.558,18.132 16.395,30.657 -1.448,15.596 -15.105,29.037 -32.729,28.094 -17.141,-0.917 -28.681,-14.862 -29.471,-29.28 -0.836,-15.238 9.33,-28.896 24.068,-32.327 0.978,-0.228 2.095,0.062 2.94,-0.708 2.239,0 4.479,0 6.72,0 z" id="path8" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 317.083,74.01 c 3.69,-1.357 7.203,-2.644 10.853,-3.487 20.368,-4.706 39.551,-1.717 57.351,9.216 17.688,10.864 29.574,26.574 37.117,45.721 3.983,10.108 6.2,20.622 6.897,31.464 1.342,20.851 -2.648,40.569 -12.809,58.881 -8.914,16.065 -21.302,28.659 -37.92,36.854 -12.342,6.086 -25.404,8.436 -39.072,7.411 -6.881,-0.516 -13.566,-2.074 -20.037,-4.512 -0.722,-0.271 -1.404,-0.789 -2.244,-0.626 -0.506,0.363 -0.334,0.885 -0.334,1.326 -0.014,8.639 -0.016,17.276 -0.006,25.914 0.002,1.954 0.028,1.975 1.961,1.983 3.519,0.017 7.039,-0.008 10.558,0.009 3.3,0.016 4.827,1.448 5.074,4.734 0.042,0.557 0.015,1.119 0.015,1.679 0,12.718 -0.001,25.435 0.003,38.151 0,1.121 0.01,2.237 -0.31,3.329 -0.545,1.861 -1.888,2.968 -3.797,3.083 -0.478,0.029 -0.959,0.007 -1.439,0.007 -28.953,0 -57.907,0.001 -86.86,-0.002 -0.719,0 -1.455,0.061 -2.153,-0.068 -1.674,-0.311 -3.035,-1.901 -3.253,-3.726 -0.066,-0.553 -0.035,-1.118 -0.035,-1.678 -0.002,-13.356 -0.003,-26.714 0.002,-40.07 10e-4,-0.953 -0.094,-1.917 0.341,-2.829 0.845,-1.771 2.22,-2.632 4.19,-2.62 3.759,0.022 7.518,0.016 11.277,0.002 1.985,-0.007 1.986,-0.024 2.021,-1.913 0.007,-0.399 0.001,-0.8 0.001,-1.200 0,-52.868 0.003,-105.736 -0.006,-158.604 -0.001,-3.582 0.482,-3.179 -3.179,-3.2 -3.199,-0.019 -6.399,0.027 -9.598,-0.013 -3.587,-0.044 -5.043,-1.541 -5.046,-5.175 -0.011,-13.517 -0.011,-27.034 0,-40.55 0.003,-3.748 1.51,-5.33 5.189,-5.333 23.275,-0.019 46.549,-0.019 69.824,0 3.653,0.005 4.788,1.232 5.424,5.841 z m 59.739,90.169 c -0.021,-8.239 -1.26,-15.685 -3.907,-22.781 -2.346,-6.289 -5.65,-11.978 -10.606,-16.616 -4.109,-3.845 -8.893,-6.285 -14.541,-6.563 -5.228,-0.257 -9.869,1.603 -14.009,4.739 -5.029,3.811 -8.625,8.785 -11.144,14.478 -7.364,16.646 -7.682,33.574 -1.499,50.625 2.262,6.237 5.732,11.767 10.702,16.261 8.65,7.822 19.684,8.163 28.784,0.894 4.134,-3.302 7.178,-7.481 9.589,-12.137 4.694,-9.06 6.592,-18.77 6.631,-28.9 z" id="path10" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d84759;fill-rule:evenodd" /><path d="m 154.325,137.344 c -0.886,-0.34 -1.612,0.068 -2.366,0.293 -3.06,0.913 -6.114,1.846 -9.186,2.719 -2.521,0.716 -4.661,-0.659 -5.076,-3.242 -0.126,-0.784 -0.125,-1.593 -0.125,-2.39 -0.007,-12.479 -0.006,-24.958 -0.002,-37.437 0,-0.719 0.013,-1.44 0.075,-2.156 0.284,-3.251 1.45,-4.731 4.576,-5.672 11.924,-3.591 23.854,-7.158 35.782,-10.737 10.782,-3.235 21.563,-6.474 32.345,-9.707 0.611,-0.183 1.224,-0.401 1.852,-0.483 2.247,-0.294 4.131,1.14 4.416,3.385 0.11,0.867 0.058,1.756 0.058,2.636 0.002,43.916 0,87.832 0.006,131.748 0,3.457 -0.295,2.951 2.898,2.976 3.359,0.025 6.72,-0.022 10.079,0.015 2.985,0.033 4.573,1.621 4.578,4.63 0.019,13.919 0.02,27.838 0,41.756 -0.004,3.195 -1.554,4.558 -5.063,4.559 -25.277,0.004 -50.555,0.002 -75.833,0.002 -3.92,0 -7.839,0.019 -11.759,-0.007 -3.161,-0.021 -4.69,-1.536 -4.694,-4.723 -0.019,-13.839 -0.02,-27.678 0.001,-41.517 0.005,-3.105 1.631,-4.679 4.773,-4.703 3.36,-0.026 6.72,0.005 10.079,-0.01 3.057,-0.013 2.741,0.321 2.748,-2.639 0.014,-5.76 0.004,-11.519 0.004,-17.278 0,-16.479 0.002,-32.957 -0.005,-49.436 -0.004,-0.867 0.115,-1.751 -0.161,-2.582 z" id="path12" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d84759;fill-rule:evenodd" /><path d="m 822.01,291.137 c 3.731,-4.199 7.707,-7.141 13.269,-6.765 5.153,0.348 9.708,1.923 12.644,6.539 1.042,-0.377 1.522,-1.156 2.14,-1.749 6.37,-6.127 15.79,-5.729 21.668,-2.641 3.534,1.858 5.53,4.979 6.719,8.683 1.236,3.854 1.466,7.837 1.467,11.846 10e-4,3.84 -0.017,7.681 0.009,11.52 0.012,1.715 0.051,1.715 1.741,1.786 2.977,0.124 2.977,0.124 2.977,3.173 10e-4,2.64 0.014,5.28 -0.004,7.92 -0.014,1.903 -0.032,1.95 -1.783,1.955 -6.96,0.02 -13.92,0.022 -20.88,-0.004 -1.684,-0.006 -1.731,-0.067 -1.731,-1.793 -0.003,-8.399 0.031,-16.799 0.027,-25.198 -10e-4,-1.749 0.082,-3.507 -0.395,-5.231 -0.443,-1.604 -1.297,-2.754 -3.038,-2.975 -1.71,-0.217 -3.524,0.844 -4.336,2.434 -0.487,0.955 -0.649,1.99 -0.657,3.024 -0.039,5.119 -0.01,10.239 -0.047,15.358 -0.008,1.105 0.352,1.635 1.521,1.56 0.956,-0.062 1.92,0.015 2.879,-0.01 0.795,-0.021 1.162,0.291 1.157,1.12 -0.018,3.52 -0.013,7.039 0.002,10.56 0.003,0.802 -0.352,1.168 -1.148,1.158 -0.88,-0.012 -1.76,0.005 -2.64,0.006 -6.319,0 -12.64,0.011 -18.959,-0.007 -1.835,-0.006 -1.871,-0.044 -1.875,-1.863 -0.016,-7.68 -0.025,-15.359 0.004,-23.039 0.01,-2.325 -0.123,-4.644 -0.48,-6.928 -0.451,-2.88 -2.993,-4.178 -5.499,-2.938 -1.441,0.713 -2.011,2.024 -2.118,3.526 -0.098,1.354 -0.067,2.718 -0.069,4.077 -0.007,4.239 -0.017,8.479 0.005,12.72 0.009,1.699 0.046,1.681 1.715,1.76 1.188,0.057 2.653,-0.496 3.494,0.263 0.966,0.873 0.372,2.427 0.406,3.68 0.062,2.238 0.035,4.479 0.012,6.72 -0.02,1.983 -0.039,2.019 -2.001,2.023 -6.72,0.018 -13.44,0.007 -20.16,0.007 -1.6,0 -3.2,-0.027 -4.8,0.006 -0.993,0.021 -1.465,-0.35 -1.455,-1.395 0.029,-3.279 0.038,-6.561 -0.004,-9.84 -0.015,-1.178 0.509,-1.579 1.605,-1.475 0.396,0.038 0.801,-0.02 1.200,0.005 0.92,0.059 1.27,-0.353 1.267,-1.273 -0.021,-6.561 -0.021,-13.12 0,-19.68 0.003,-0.924 -0.351,-1.332 -1.27,-1.271 -0.318,0.021 -0.64,0.01 -0.959,-0.003 -1.751,-0.07 -1.802,-0.072 -1.819,-1.709 -0.036,-3.199 0.015,-6.399 -0.022,-9.6 -0.013,-1.139 0.436,-1.531 1.57,-1.521 5.04,0.043 10.08,0.041 15.12,-0.001 1.038,-0.009 1.609,0.31 1.929,1.334 0.399,1.289 0.967,2.527 1.603,4.146 z" id="path14" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 771.929,284.634 c 4.882,-0.165 9.529,0.805 13.86,3.088 7.266,3.829 11.206,9.983 12.411,17.995 0.564,3.753 0.454,7.5 -0.507,11.176 -1.902,7.287 -6.343,12.521 -13.299,15.335 -9.175,3.712 -18.288,3.34 -26.994,-1.519 -6.144,-3.429 -9.838,-8.775 -11.076,-15.71 -1.158,-6.483 -0.608,-12.774 2.931,-18.499 4.534,-7.336 11.405,-10.912 19.8,-11.853 0.946,-0.105 1.916,-0.013 2.874,-0.013 z m -6.391,24.775 c -0.107,3.785 0.474,7.305 2.617,10.359 2.446,3.488 6.324,3.311 8.541,-0.314 0.371,-0.606 0.68,-1.269 0.908,-1.941 1.659,-4.902 1.658,-9.853 0.27,-14.805 -0.41,-1.46 -1.109,-2.816 -2.18,-3.938 -2.351,-2.46 -5.304,-2.33 -7.375,0.345 -0.585,0.755 -1.045,1.584 -1.417,2.479 -1.061,2.548 -1.489,5.192 -1.364,7.815 z" id="path16" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 729.656,289.509 c 0.584,-0.451 0.601,-1.232 0.859,-1.868 0.818,-2.012 0.785,-2.032 2.81,-2.038 2.559,-0.008 5.117,0.032 7.674,-0.018 1.063,-0.02 1.596,0.204 1.586,1.44 -0.052,6.235 -0.043,12.472 -0.006,18.707 0.007,1.103 -0.301,1.579 -1.483,1.563 -3.756,-0.054 -7.515,-0.046 -11.271,-0.004 -1.031,0.011 -1.438,-0.424 -1.492,-1.392 -0.084,-1.523 -0.395,-3.006 -0.914,-4.442 -1.956,-5.411 -6.595,-4.962 -9.705,-2.203 -0.355,0.314 -0.685,0.688 -0.93,1.094 -2.861,4.745 -3.563,9.704 -1.201,14.846 1.626,3.538 4.556,5.237 8.396,5.365 3.974,0.132 7.304,-1.371 10.151,-4.092 1.373,-1.31 1.423,-1.286 2.68,0.149 1.895,2.164 3.764,4.352 5.647,6.524 1.602,1.848 1.626,1.868 -0.294,3.557 -4.105,3.609 -8.873,5.972 -14.185,7.147 -6.294,1.394 -12.559,1.24 -18.607,-1.169 -8.834,-3.52 -13.651,-10.162 -14.835,-19.532 -0.559,-4.418 -0.324,-8.794 1.039,-13.034 2.766,-8.596 8.829,-13.423 17.606,-14.972 2.042,-0.359 4.108,-0.59 6.202,-0.336 3.434,0.416 6.469,1.683 9.041,4.019 0.336,0.306 0.596,0.774 1.232,0.689 z" id="path18" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /><path d="m 681.92,333.677 c -6.156,0.305 -10.938,-5.175 -10.884,-10.395 0.062,-5.965 4.89,-10.776 10.845,-10.808 6.384,-0.033 10.988,5.186 11.006,10.937 0.022,7.141 -5.384,10.556 -10.967,10.266 z" id="path20" inkscape:connector-curvature="0" style="clip-rule:evenodd;fill:#d8485a;fill-rule:evenodd" /></svg>' : '<img class="comic-image-nav" src="' . esc_attr($tipeee_image_button_url) . '" />';

                $html             = "";
                $buymeacoffee_url = get_option("toocheke-support-link-buymeacoffee");
                $gumroad_url      = get_option("toocheke-support-link-gumroad");
                $indiegogo_url    = get_option("toocheke-support-link-indiegogo");
                $kickstarter_url  = get_option("toocheke-support-link-kickstarter");
                $kofi_url         = get_option("toocheke-support-link-kofi");
                $liberapay_url    = get_option("toocheke-support-link-liberapay");
                $patreon_url      = get_option("toocheke-support-link-patreon");
                $paypal_url       = get_option("toocheke-support-link-paypal");
                $substack_url     = get_option("toocheke-support-link-substack");
                $tipeee_url       = get_option("toocheke-support-link-tipeee");

                if (! empty($buymeacoffee_url)) {
                    $html = $html . "<a href='" . esc_url($buymeacoffee_url) . "' title='Support with Buy me a coffee' target='_blank'>" . wp_kses($buymeacoffee_button, $allowed_tags) . "</a>";
                }

                if (! empty($gumroad_url)) {
                    $html = $html . "<a href='" . esc_url($gumroad_url) . "' title='Support with Gumroad' target='_blank'>" . wp_kses($gumroad_button, $allowed_tags) . "</a>";
                }

                if (! empty($indiegogo_url)) {
                    $html = $html . "<a href='" . esc_url($indiegogo_url) . "' title='Support with Indiegogo' target='_blank'>" . wp_kses($indiegogo_button, $allowed_tags) . "</a>";
                }

                if (! empty($kickstarter_url)) {
                    $html = $html . "<a href='" . esc_url($kickstarter_url) . "' title='Support with Kickstarter' target='_blank'>" . wp_kses($kickstarter_button, $allowed_tags) . "</a>";
                }
                if (! empty($kofi_url)) {
                    $html = $html . "<a href='" . esc_url($kofi_url) . "' title='Support with Ko-fi' target='_blank'>" . wp_kses($kofi_button, $allowed_tags) . "</a>";
                }

                if (! empty($liberapay_url)) {
                    $html = $html . "<a href='" . esc_url($liberapay_url) . "' title='Support with Liberapay' target='_blank'>" . wp_kses($liberapay_button, $allowed_tags) . "</a>";
                }

                if (! empty($patreon_url)) {
                    $html = $html . "<a href='" . esc_url($patreon_url) . "' title='Support with Patreon' target='_blank'>" . wp_kses($patreon_button, $allowed_tags) . "</a>";
                }

                if (! empty($paypal_url)) {
                    $html = $html . "<a href='" . esc_url($paypal_url) . "' title='Support with PayPal' target='_blank'>" . wp_kses($paypal_button, $allowed_tags) . "</a>";
                }

                if (! empty($substack_url)) {
                    $html = $html . "<a href='" . esc_url($substack_url) . "' title='Support with Substack' target='_blank'>" . wp_kses($substack_button, $allowed_tags) . "</a>";
                }
                if (! empty($tipeee_url)) {
                    $html = $html . "<a href='" . esc_url($tipeee_url) . "' title='Support with Tipeee' target='_blank'>" . wp_kses($tipeee_button, $allowed_tags) . "</a>";
                }
                echo $html;
            }

            public function toocheke_update_edit_form()
            {
                echo ' enctype="multipart/form-data"';
            }

            public function toocheke_frontend_styles_and_scripts()
            {
                //enqueue keyboard nav js.
                $disable_keyboard = get_option('toocheke-keyboard') && 1 == get_option('toocheke-keyboard');
                if (! $disable_keyboard):
                    wp_enqueue_script('toocheke-keyboard-script', plugins_url('toocheke-companion' . '/js/keyboard.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                    wp_enqueue_script('toocheke-keyboard-script');
                endif;
                //bookmark
                wp_enqueue_script('toocheke-bookmark-script', plugins_url('toocheke-companion' . '/js/bookmark.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                wp_enqueue_script('toocheke-bookmark-script');
                //likes
                wp_enqueue_script('toocheke-likes', plugins_url('toocheke-companion' . '/js/likes.js'), ['jquery'], TOOCHEKE_COMPANION_VERSION, true);
                wp_localize_script('toocheke-likes', 'toochekeLikes', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'like'    => __('Like', 'toocheke-companion'),
                    'unlike'  => __('Unlike', 'toocheke-companion'),
                ]);
                wp_register_style('toocheke-companion-likes', plugins_url('toocheke-companion' . '/css/toocheke-likes.css'));
                wp_enqueue_style('toocheke-companion-likes');

                //optional Font-awesome
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    wp_register_style('toocheke-font-awesome', plugins_url('toocheke-companion/fonts/font-awesome/css/all.min.css'));
                    wp_enqueue_style('toocheke-font-awesome');
                    wp_register_style('toocheke-universal-styles', plugins_url('toocheke-companion/css/universal.css'), [], TOOCHEKE_COMPANION_VERSION);
                    wp_enqueue_style('toocheke-universal-styles');
                    wp_register_style('toocheke-manga-styles', plugins_url('toocheke-companion/css/manga.css'), [], TOOCHEKE_COMPANION_VERSION);
                    wp_enqueue_style('toocheke-manga-styles');
                }
            }

            /**
             * Conditionally enqueue Swiper & Fullscreen libraries
             */
            public function toocheke_enqueue_reader_libraries()
            {
                global $post;

                if (! is_singular()) {
                    return;
                }

                if (! $post instanceof WP_Post) {
                    return;
                }

                $post_type = get_post_type($post);

                // Check if it's manga_volume with ?reader param
                if ($post_type === 'manga_volume' && isset($_GET['reader'])) {
                    $this->toocheke_enqueue_swiper_and_fullscreen();
                }

                // Always enqueue for manga_chapter
                if ($post_type === 'manga_chapter') {
                    $this->toocheke_enqueue_swiper_and_fullscreen();
                }
            }

            /**
             * Helper function to enqueue Swiper & Fullscreen
             */
            private function toocheke_enqueue_swiper_and_fullscreen()
            {
                $plugin_url = TOOCHEKE_COMPANION_PLUGIN_URL;

                // Swiper CSS
                wp_enqueue_style(
                    'toocheke-swiper',
                    $plugin_url . 'assets/swiper/swiper-bundle.min.css',
                    [],
                    TOOCHEKE_COMPANION_VERSION
                );

                // Swiper JS
                wp_enqueue_script(
                    'toocheke-swiper',
                    $plugin_url . 'assets/swiper/swiper-element-bundle.min.js',
                    [],
                    TOOCHEKE_COMPANION_VERSION,
                    true
                );

                // Fullscreen JS
                wp_enqueue_script(
                    'toocheke-fullscreen',
                    $plugin_url . 'assets/fullscreen/jquery.fullscreen.min.js',
                    ['jquery'],
                    TOOCHEKE_COMPANION_VERSION,
                    true
                );

                // Fullscreen JS
                wp_enqueue_script(
                    'manga',
                    $plugin_url . 'js/manga.js',
                    ['jquery'],
                    TOOCHEKE_COMPANION_VERSION,
                    true
                );
            }

            /**
             * Add Series ID for comments if it exits
             */
            public function toocheke_redirect_comments($location, $commentdata)
            {
                if ((isset($_REQUEST['series_id'])) && ($_REQUEST['series_id'] != '')) {
                    $location = add_query_arg("sid", $_REQUEST['series_id'], $location);
                }
                return $location;
            }

            public function toocheke_rewrite_series_comic_permalink($permalink, $post, $leavename)
            {
                $post_id = $post->ID;

                if ($post->post_type != 'comic' || empty($permalink) || in_array($post->post_status, ['draft', 'pending', 'auto-draft']) || ! isset($_GET['sid'])) {
                    return $permalink;
                }

                $parent      = $post->post_parent;
                $parent_post = get_post($parent);
                //$permalink =  home_url( 'series/' . $parent_post->post_name . '/comic/' . $post->post_name );
                //$permalink = str_replace('comic', 'series', $permalink);

                return $permalink;
            }

            /**
             * Add fields for logged in commenter
             */
            public function toocheke_add_logged_in_fields()
            {
                if (is_user_logged_in()) {
                    $series_id = null;
                    $series_id = isset($_GET['sid']) ? (int) $_GET['sid'] : null;
                    if ($series_id != null) {
                        echo '<input id="series_id" name="series_id" type="hidden" value="' . esc_attr($series_id) . '" />';
                    }
                }
            }

            /**
             * Modify excerpt length
             */
            public function toocheke_excerpt_length($length)
            {
                return 40;
            }

            /**
             * Remove paragraphs from  comic post types
             */
            public function toocheke_remove_autop_for_comic($content)
            {
                'comic' === get_post_type() && remove_filter('the_content', 'wpautop');
                return $content;
            }

            /**
             * Age Verification Popup
             */
            public function toocheke_verify_age_popup()
            {
                $verify_age = get_option('toocheke-age-verification') && 1 == get_option('toocheke-age-verification');

                $series_id = $comic_id = 0;
                if (is_singular('comic') || get_post_type() === 'comic' || is_home()) {
                    if (is_singular('comic')) {
                        global $post;
                        $comic_id = get_the_ID();
                        if ($post->post_parent) {
                            $series_id = $post->post_parent;
                        }
                    }
                    if (is_home()) {
                        $home_layout     = get_theme_mod('home_layout_setting', 'default');
                        $webtoon_layouts = ["default", "alt-3", "alt-5"];
                        if (! in_array($home_layout, $webtoon_layouts)) {
                            $comic_order        = get_option('toocheke-comics-order') ? get_option('toocheke-comics-order') : 'DESC';
                            $single_comics_args = [
                                'post_type'      => 'comic',
                                'post_status'    => 'publish',
                                'posts_per_page' => 1,
                                'orderby'        => 'post_date',
                                'order'          => $comic_order,
                            ];
                            $single_comic_query = new WP_Query($single_comics_args);
                            while ($single_comic_query->have_posts()): $single_comic_query->the_post();
                                $comic_id  = get_the_ID();
                                $series_id = wp_get_post_parent_id($comic_id);
                            endwhile;
                            wp_reset_postdata();
                        }
                    }
                }
                if ($comic_id) {
                    $verify_age = get_post_meta($comic_id, 'age-verification', true) ? 1 : $verify_age;
                }
                if ($series_id) {
                    $verify_age = get_post_meta($series_id, 'age-verification', true) ? 1 : $verify_age;
                }

                if ($verify_age) {
                    if (! isset($_COOKIE['toocheke_age_verification'])) { ?>
                <div class="modal" id="age-verification-modal" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">


                            <!-- Modal body -->
                            <div class="modal-body p-4">

                                <h3 class="text-center">Verify Your Age</h3>
                                <hr />
                                <p class="text-center">You must be <b class="text-danger">18 years</b> or older to continue
                                    browsing this website. Are you at least 18 years old?</p>
                                <p class="text-center">
                                    <button id="btn-18-yes" type="button" class="btn btn-success">Yes</button>
                                    <button id="btn-18-no" type="button" class="btn btn-secondary">Close</button>
                                </p>

                            </div>


                        </div>
                    </div>
                </div>
                <style>
                    .modal-backdrop {
                        opacity: 0.9;
                    }
                </style>
        <?php }
                }
            }

            public function toocheke_remove_image_link()
            {
                $image_set = get_option('image_default_link_type');

                if ($image_set !== 'none') {
                    update_option('image_default_link_type', 'none');
                }
            }

            public function toocheke_attachment_image_link_remove_filter($content)
            {
                $allow_image_click = get_option('toocheke-image-click') && 1 == get_option('toocheke-image-click');

                if (! $allow_image_click) {
                    if (is_singular('comic') || get_post_type() === 'comic') {
                        $content =
                            preg_replace(
                                [
                                    '{<a(.*?)(wp-att|wp-content\/uploads)[^>]*><img}',
                                    '{ wp-image-[0-9]*" /></a>}'
                                ],
                                ['<img', '" />'],
                                $content
                            );
                    }
                }
                return $content;
            }

            public function toocheke_add_bookmark_nav_item($items, $args)
            {
                $display_bookmark_button = get_option('toocheke-comic-bookmark') && 1 == get_option('toocheke-comic-bookmark');
                if ($display_bookmark_button) {
                    $items .= '<li class="nav-item menu-item comic-bookmark-item"> <a id="toocheke-go-to-bookmark" class="nav-link" href="javascript:;"><i class="fas fa-lg fa-bookmark"" aria-hidden="true"></i></a></li>';
                }
                return $items;
            }

            public function toocheke_single_comic_template($template)
            {
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    global $post;

                    if (get_post_type($post) !== 'comic' && ! is_single()) {
                        return $template;
                    }
                    add_filter('post_thumbnail_html', [$this, 'toocheke_disable_post_thumbnail'], 500, 2);
                    add_filter('the_content', [$this, 'toocheke_universal_single_comic_content_filter']);
                }
                return $template;
            }

            public function toocheke_disable_post_thumbnail($html, $post_id)
            {
                if (get_post_type($post_id) == 'comic') {
                    return '';
                }

                return $html;
            }

            public function toocheke_universal_single_comic_content_filter($content)
            {
                global $post;
                $templates = new Toocheke_Companion_Template_Loader;
                if (get_post_type($post) !== 'comic') {
                    return $content;
                }

                remove_filter('the_content', 'toocheke_universal_single_comic_content_filter');
                remove_filter('post_thumbnail_html', 'toocheke_disable_post_thumbnail');

                ob_start();
                require TOOCHEKE_COMPANION_PLUGIN_DIR . 'templates/content-singlecomic.php';
                $generated_content = ob_get_contents();
                ob_end_clean();
                $content = $generated_content;

                return $content;
            }

            public function toocheke_single_manga_templates($template)
            {
                $theme = wp_get_theme(); // gets the current theme
                if (
                    'Toocheke Premium' !== $theme->name &&
                    'Toocheke Premium' !== $theme->parent_theme &&
                    'Toocheke' !== $theme->name &&
                    'Toocheke' !== $theme->parent_theme
                ) {

                    global $post;

                    // Only handle our three CPTs on single pages
                    $allowed_types = ['manga_series', 'manga_volume', 'manga_chapter'];
                    if (! is_single() || ! in_array(get_post_type($post), $allowed_types, true)) {
                        return $template;
                    }

                    // Disable thumbnail and override content
                    add_filter('post_thumbnail_html', [$this, 'toocheke_disable_post_thumbnail'], 500, 2);
                    add_filter('the_content', [$this, 'toocheke_universal_single_manga_content_filter']);
                }

                return $template;
            }

            public function toocheke_universal_single_manga_content_filter($content)
            {
                global $post;

                $post_type     = get_post_type($post);
                $template_file = '';

                switch ($post_type) {
                    case 'manga_series':
                        $template_file = 'templates/content-singlemangaseries.php';
                        break;

                    case 'manga_volume':
                        if (isset($_GET['reader']) && $_GET['reader'] === 'true') {
                            $template_file = 'templates/content-singlemangavolumereader.php';
                        } else {
                            $template_file = 'templates/content-singlemangavolume.php';
                        }
                        break;

                    case 'manga_chapter':
                        $template_file = 'templates/content-singlemangachapterreader.php';
                        break;

                    default:
                        return $content; // bail if not ours
                }

                // Make sure we don’t create infinite loops
                remove_filter('the_content', [$this, 'toocheke_universal_single_manga_content_filter']);
                remove_filter('post_thumbnail_html', [$this, 'toocheke_disable_post_thumbnail']);

                // Load template output
                ob_start();
                require TOOCHEKE_COMPANION_PLUGIN_DIR . $template_file;
                $generated_content = ob_get_clean();

                return $generated_content ?: $content;
            }

            public function toocheke_universal_excerpt_length($length)
            {
                global $post;
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    if ($post->post_type == 'series') {
                        return 15;
                    } else {
                        return $length;
                    }
                }
                return $length;
            }

            /**
             * Generate date archive rewrite rules for comic
             * @param  string $cpt slug of the custom post type
             * @return rules       returns a set of rewrite rules for WordPress to handle
             */
            public function toocheke_universal_generate_date_archives($cpt, $wp_rewrite)
            {
                $rules = [];

                $post_type    = get_post_type_object($cpt);
                $slug_archive = $post_type->has_archive;
                if ($slug_archive === false) {
                    return $rules;
                }
                if ($slug_archive === true) {
                    // Here's my edit to the original function, let's pick up
                    // custom slug from the post type object if user has
                    // specified one.
                    $slug_archive = $post_type->rewrite['slug'];
                }

                $dates = [
                    [
                        'rule' => "([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})",
                        'vars' => ['year', 'monthnum', 'day'],
                    ],
                    [
                        'rule' => "([0-9]{4})/([0-9]{1,2})",
                        'vars' => ['year', 'monthnum'],
                    ],
                    [
                        'rule' => "([0-9]{4})",
                        'vars' => ['year'],
                    ],
                ];

                foreach ($dates as $data) {
                    $query = 'index.php?post_type=' . $cpt;
                    $rule  = $slug_archive . '/' . $data['rule'];

                    $i = 1;
                    foreach ($data['vars'] as $var) {
                        $query .= '&' . $var . '=' . $wp_rewrite->preg_index($i);
                        $i++;
                    }

                    $rules[$rule . "/?$"]                               = $query;
                    $rules[$rule . "/feed/(feed|rdf|rss|rss2|atom)/?$"] = $query . "&feed=" . $wp_rewrite->preg_index($i);
                    $rules[$rule . "/(feed|rdf|rss|rss2|atom)/?$"]      = $query . "&feed=" . $wp_rewrite->preg_index($i);
                    $rules[$rule . "/page/([0-9]{1,})/?$"]              = $query . "&paged=" . $wp_rewrite->preg_index($i);
                }
                return $rules;
            }

            public function toocheke_universal_get_post_views($postID)
            {
                $count_key = 'post_views_count';
                $count     = get_post_meta($postID, $count_key, true);
                if ($count == '') {
                    delete_post_meta($postID, $count_key);
                    add_post_meta($postID, $count_key, '0');
                    return "0";
                }
                return $count;
            }

            public function toocheke_universal_set_post_views()
            {
                if (!is_singular(['comic', 'manga_chapter'])) {
                    return; // Only run on comic or manga_chapter CPTs
                }

                $postID = get_the_ID();
                if (! $postID) {
                    return; // Safety check
                }

                // --- Theme exclusion ---
                $theme = wp_get_theme();
                $theme_names = ['Toocheke Premium', 'Toocheke'];
                if (in_array($theme->name, $theme_names, true) || in_array($theme->parent_theme, $theme_names, true)) {
                    return;
                }

                // --- Bot check ---
                $bots = ['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'wget', 'curl'];
                $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
                foreach ($bots as $bot) {
                    if (strpos($user_agent, $bot) !== false) {
                        return; // Detected bot — skip counting
                    }
                }

                // --- Cookie setup ---
                $cookie_name = 'toocheke_viewed_' . $postID;

                if (! isset($_COOKIE[$cookie_name])) {
                    $count_key = 'post_views_count';
                    $count     = (int) get_post_meta($postID, $count_key, true);

                    $count++;
                    update_post_meta($postID, $count_key, $count);

                    // Set cookie to expire in 7 days
                    setcookie(
                        $cookie_name,
                        '1',
                        time() + (7 * DAY_IN_SECONDS),
                        COOKIEPATH ?: '/',
                        COOKIE_DOMAIN,
                        is_ssl(),
                        true
                    );
                }
            }

            public function toocheke_comic_archive_template($template)
            {
                $theme = wp_get_theme(); // gets the current theme
                if ('Toocheke Premium' !== $theme->name && 'Toocheke Premium' !== $theme->parent_theme && 'Toocheke' !== $theme->name && 'Toocheke' !== $theme->parent_theme) {
                    global $post;
                    $output    = '';
                    $templates = new Toocheke_Companion_Template_Loader;
                    if (! is_post_type_archive('comic')) {
                        return $template;
                    }

                    $template = TOOCHEKE_COMPANION_PLUGIN_DIR . 'templates/content-comicdefaultarchive.php';

                    return $template;
                }
                return $template;
            }

            public function toocheke_posted_on()
            {
                $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
                if (get_the_time('U') !== get_the_modified_time('U')) {
                    $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
                }

                $time_string = sprintf(
                    $time_string,
                    esc_attr(get_the_date(DATE_W3C)),
                    esc_html(get_the_date()),
                    '',
                    ''
                );

                $posted_on = sprintf(
                    /* translators: %s: post date. */
                    esc_html_x('Posted on %s', 'post date', 'toocheke-companion'),
                    '<a href="' . esc_url(get_permalink()) . '" rel="bookmark">' . $time_string . '</a>'
                );

                echo '<span class="posted-on">' . wp_kses_data($posted_on) . '</span>';
            }

            public function toocheke_posted_by()
            {
                $byline = sprintf(
                    /* translators: %s: post author. */
                    esc_html_x('by %s', 'post author', 'toocheke-companion'),
                    '<span class="author vcard"><a class="url fn n" href="' . esc_url(get_author_posts_url(get_the_author_meta('ID'))) . '">' . esc_html(get_the_author()) . '</a></span>'
                );

                echo '<span class="byline"> ' . wp_kses_data($byline) . '</span>';
            }

            public function toocheke_random_template()
            {
                if (get_query_var('random') == 1) {
                    $args = [
                        'post_type'      => 'comic',
                        'orderby'        => 'rand',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                    ];

                    $sid = absint(get_query_var('sid'));
                    if ($sid > 0) {
                        $args['post_parent'] = $sid;
                    }

                    $posts = get_posts($args);

                    if (! empty($posts)) {
                        $post = reset($posts);
                        $link = get_permalink($post);

                        // Append sid to the permalink if provided
                        if ($sid > 0) {
                            $link = add_query_arg('sid', $sid, $link);
                        }
                        if (get_option('toocheke-scroll-past-header') && 1 == get_option('toocheke-scroll-past-header')) {
                            $link = $link . '#main';
                        }

                        wp_redirect($link, 307);
                        exit;
                    }
                }
            }

            public function toocheke_extend_search($search, $query)
            {
                global $wpdb;

                if ($query->is_main_query() && ! empty($query->query['s'])) {
                    $sql = "
                or exists (
                    select * from {$wpdb->postmeta} where post_id={$wpdb->posts}.ID
                    and meta_key in ('desktop_comic_editor', 'comic_blog_post_editor', 'mobile_comic_2nd_language_editor', 'comic_2nd_language_blog_post_editor', 'desktop_comic_2nd_language_editor', 'transcript')
                    and meta_value like %s
                )
            ";
                    $like   = '%' . $wpdb->esc_like($query->query['s']) . '%';
                    $search = preg_replace(
                        "#\({$wpdb->posts}.post_title LIKE [^)]+\)\K#",
                        $wpdb->prepare($sql, $like),
                        $search
                    );
                }

                return $search;
            }

            /*
        * Alt hover for images
        */
            // Shared logic to insert hovertext into image tags
            public function toocheke_add_hovertext_to_images_in_html($html, $hovertext)
            {
                if (empty($html) || empty($hovertext)) {
                    return '';
                }

                libxml_use_internal_errors(true);
                $dom      = new DOMDocument();
                $encoding = '<?xml encoding="utf-8" ?>';
                $dom->loadHTML($encoding . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $images = $dom->getElementsByTagName('img');
                foreach ($images as $img) {
                    if (! $img->hasAttribute('title')) {
                        $img->setAttribute('title', $hovertext);
                    }
                    // Always overwrite alt attribute
                    $img->setAttribute('alt', $hovertext);
                }

                $modified = $dom->saveHTML();
                $modified = preg_replace('/^<\?xml.*?\?>/', '', $modified);

                return $modified;
            }

            // Filter for the_content (only for 'comic' post type)
            public function toocheke_add_comic_hovertext_to_content($content)
            {
                // Get home layout setting (with 'default' as fallback)
                $home_layout = get_theme_mod('home_layout_setting', 'default');
                // Determine if comics should be displayed on the home page
                $display_comic_on_home_page = in_array($home_layout, ['alt-1', 'alt-2', 'alt-4']);

                // Check if we are not on a singular comic, in the main query, and on the homepage with the correct layout
                if (
                    ! is_singular('comic') &&                    // Not a singular 'comic'
                    ! in_the_loop() &&                           // Not in the loop
                    ! is_main_query() &&                         // Not the main query
                    ! (is_home() && $display_comic_on_home_page) // Not the homepage with comics enabled
                ) {
                    return $content;
                }

                $post_id = 0;

                if (is_singular('comic')) {
                    $post_id = get_the_ID();
                }
                if (is_home()) {
                    // Access the global $post object
                    global $post;

                    // Check if this is a 'comic' post type
                    if ('comic' === $post->post_type) {
                        // Get the post ID
                        $post_id = $post->ID;
                    }
                }

                $hovertext = get_post_meta($post_id, 'comic-hovertext', true);

                if (empty($hovertext)) {
                    return $content; // No hovertext, return nothing
                }

                return $this->toocheke_add_hovertext_to_images_in_html($content, $hovertext);
            }

            private function toocheke_render_thumbnail($post_id)
            {
                $post_thumbnail_id = get_post_thumbnail_id($post_id);
                if ($post_thumbnail_id) {
                    $img = wp_get_attachment_image_src($post_thumbnail_id, 'featured_preview');
                    $src = $img ? $img[0] : plugins_url('toocheke-companion/img/no-image.png');
                } else {
                    $src = plugins_url('toocheke-companion/img/no-image.png');
                }
                echo '<img src="' . esc_url($src) . '" class="comic-thumbnail" />';
            }

/**
 * Sanitize rich text content while allowing YouTube embeds
 */
private function toocheke_sanitize_rich_text_with_embeds($content)
{
    // Get all allowed tags from wp_kses_post
    $allowed_tags = wp_kses_allowed_html('post');
    
    // Add iframe with YouTube-specific attributes
    $allowed_tags['iframe'] = array(
        'src'             => true,
        'width'           => true,
        'height'          => true,
        'frameborder'     => true,
        'allowfullscreen' => true,
        'allow'           => true,
        'title'           => true,
        'class'           => true,
        'id'              => true,
        'style'           => true,
    );
    
    // Sanitize with custom allowed tags
    $sanitized = wp_kses($content, $allowed_tags);
    
    // Additional security: ensure iframe src only allows YouTube domains
    $sanitized = preg_replace_callback(
        '/<iframe([^>]*)src=["\']([^"\']*)["\']([^>]*)>/i',
        function($matches) {
            $src = $matches[2];
            $allowed_domains = array(
                'youtube.com',
                'www.youtube.com',
                'youtube-nocookie.com',
                'www.youtube-nocookie.com'
            );
            
            $parsed = parse_url($src);
            if (isset($parsed['host'])) {
                foreach ($allowed_domains as $domain) {
                    if (strpos($parsed['host'], $domain) !== false) {
                        return '<iframe' . $matches[1] . 'src="' . esc_url($src) . '"' . $matches[3] . '>';
                    }
                }
            }
            
            // If not from allowed domain, remove the iframe
            return '';
        },
        $sanitized
    );
    
    return $sanitized;
}

}
