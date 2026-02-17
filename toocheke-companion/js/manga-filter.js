jQuery(document).ready(function ($) {
    $('.manga-series-filter-form').on('submit', function () {
        var $btn = $(this).find('button[type="submit"]');

        $btn.addClass('disabled')
            .prop('disabled', true)
            .html('<span class="manga-button-spinner"></span> Loading...');
    });
});