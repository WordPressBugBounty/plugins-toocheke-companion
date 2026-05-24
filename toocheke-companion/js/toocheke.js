jQuery(document).ready(function($) {
    // Open ComicScout link in new tab
    $('.toocheke-comicscout-link > a').attr('target', '_blank');

    // --- Manga Chapters: repopulate Volume dropdown when Series changes ---
    var $seriesSelect = $('#filter-by-manga-series');
    var $volumeSelect = $('#filter-by-manga-volume');

    if ($seriesSelect.length && $volumeSelect.length) {

        $seriesSelect.on('change', function() {
            var seriesId = $(this).val();
            var currentVolume = $volumeSelect.val();

            // Reset and show loading state
            $volumeSelect.empty().append(
                $('<option>', { value: '0', text: toochekeMangaAdmin.allVolumes })
            );
            $volumeSelect.prop('disabled', true);

            if (!seriesId || seriesId === '0') {
                $volumeSelect.prop('disabled', false);
                return;
            }

            $.ajax({
                url: toochekeMangaAdmin.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'toocheke_get_volumes_by_series',
                    series_id: seriesId,
                    nonce: toochekeMangaAdmin.nonce,
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(i, volume) {
                            var $option = $('<option>', {
                                value: volume.id,
                                text:  volume.title,
                            });
                            if (volume.id == currentVolume) {
                                $option.prop('selected', true);
                            }
                            $volumeSelect.append($option);
                        });
                    }
                },
                error: function() {
                    // Silent fail — dropdown stays with just "All Volumes"
                },
                complete: function() {
                    $volumeSelect.prop('disabled', false);
                }
            });
        });
    }
});