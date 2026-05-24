jQuery(document).ready(function ($) {
    $.urlParam = function (name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        return results[1] || 0;
    }

    function openMenu(menuClass, taxonomyHref) {
        $('.' + menuClass)
            .removeClass('wp-not-current-submenu')
            .addClass('wp-has-current-submenu wp-menu-open');

        if (taxonomyHref) {
            $('.' + menuClass)
                .find('li').has('a[href*="' + taxonomyHref + '"]')
                .addClass('current');
        }
    }

    switch ($.urlParam('taxonomy')) {

        // Series taxonomies → toocheke-series-hub
        case 'series_tags':
            openMenu('toplevel_page_toocheke-series-hub', 'edit-tags.php?taxonomy=series_tags');
            break;
        case 'genres':
            openMenu('toplevel_page_toocheke-series-hub', 'edit-tags.php?taxonomy=genres');
            break;

        // Comic taxonomies → toocheke-comics-hub
        case 'collections':
            openMenu('toplevel_page_toocheke-comics-hub', 'edit-tags.php?taxonomy=collections');
            break;
        case 'chapters':
            openMenu('toplevel_page_toocheke-comics-hub', 'edit-tags.php?taxonomy=chapters');
            break;
        case 'comic_tags':
            openMenu('toplevel_page_toocheke-comics-hub', 'edit-tags.php?taxonomy=comic_tags');
            break;
        case 'comic_locations':
            openMenu('toplevel_page_toocheke-comics-hub', 'edit-tags.php?taxonomy=comic_locations');
            break;
        case 'comic_characters':
            openMenu('toplevel_page_toocheke-comics-hub', 'edit-tags.php?taxonomy=comic_characters');
            break;

        // Manga Series taxonomies → toocheke-manga-hub
        case 'manga_genre':
            openMenu('toplevel_page_toocheke-manga-hub', 'edit-tags.php?taxonomy=manga_genre');
            break;
        case 'manga_publisher':
            openMenu('toplevel_page_toocheke-manga-hub', 'edit-tags.php?taxonomy=manga_publisher');
            break;

        default:
            break;
    }
});