<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! function_exists('toocheke_companion_archive_post_status')) {
    function toocheke_companion_archive_post_status()
    {
        return function_exists('toocheke_user_has_early_access') && get_option('toocheke-early-access') ? array('publish', 'future') : 'publish';
    }
}

if (! function_exists('toocheke_companion_comic_is_linkable')) {
    function toocheke_companion_comic_is_linkable($comic = null)
    {
        $comic = get_post($comic);
        return $comic && ('future' !== $comic->post_status || (function_exists('toocheke_user_has_early_access') && toocheke_user_has_early_access()));
    }
}

if (! function_exists('toocheke_companion_comic_link_open')) {
    function toocheke_companion_comic_link_open($url, $comic = null, $title = '')
    {
        if (! toocheke_companion_comic_is_linkable($comic)) {
            echo '<span>';
            return;
        }
        echo '<a href="' . esc_url($url) . '"' . ($title ? ' title="' . esc_attr($title) . '"' : '') . '>';
    }
}

if (! function_exists('toocheke_companion_comic_link_close')) {
    function toocheke_companion_comic_link_close($comic = null)
    {
        echo toocheke_companion_comic_is_linkable($comic) ? '</a>' : '</span>';
    }
}
