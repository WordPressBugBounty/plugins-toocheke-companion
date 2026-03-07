jQuery(document).ready(function ($) {
	/* Comics Navigation  Images */
	var mediaUploader;
	var hiddenField = "";
	var imageField = "";

	if ($('#toocheke-comics-navigation').is(":checked")) {
		$('#toocheke-comics-navigation').closest('tr').siblings().hide();
	}

	$('.upload-custom-button').on('click', function (e) {
		e.preventDefault();
		hiddenField = $(this).data('hidden');
		imageField = $(this).data('image');
		if (mediaUploader) {
			mediaUploader.open();
			return;
		}

		mediaUploader = wp.media.frames.file_frame = wp.media({
			title: 'Select an Image For Your Button',
			button: {
				text: 'Choose this image'
			},
			multiple: false
		});

		mediaUploader.on('select', function () {

		});

		mediaUploader.on('close', function () {
			attachment = mediaUploader.state().get('selection').first().toJSON();
			$('#' + hiddenField).val(attachment.url);
			$('#' + imageField).attr("src", attachment.url);
			hiddenField = "";
			imageField = "";
		});

		mediaUploader.open();

	});

	$('#toocheke-comics-navigation').click(function () {
		if ($(this).is(":checked")) {
			$(this).closest('tr').siblings().animate({ height: 'toggle', opacity: 'toggle' }, 'slow');
		}
		else if ($(this).is(":not(:checked)")) {
			$(this).closest('tr').siblings().animate({ height: 'toggle', opacity: 'toggle' }, 'slow');
		}
	});
/* Comic Post Media */
jQuery(function ($) {

    /* Featured image ratio guide toggle */
    var guide = $('#toocheke-featured-image-ratio-guide');

    function toggleGuide() {
        if (!guide.length) return;
        var hasImage = $('#postimagediv .inside img').length > 0;
        guide.toggle(!hasImage);
    }

    if (guide.length) {
        toggleGuide();

        var target = document.querySelector('#postimagediv .inside');
        if (target) {
            new MutationObserver(toggleGuide).observe(target, { childList: true, subtree: true });
        }

        $(document).on('click', '#postimagediv a', function () {
            setTimeout(toggleGuide, 300);
        });
    }

    /**
     * Generic image metabox uploader factory.
     *
     * @param {string} metaboxId      - The metabox wrapper element ID  (e.g. 'comicscout-image-metabox')
     * @param {string} inputId        - The hidden input ID              (e.g. 'comicscout_image')
     * @param {string} uploadBtnId    - The upload link ID               (e.g. 'upload_comicscout_image_button')
     * @param {string} removeBtnId    - The remove link ID               (e.g. 'remove_comicscout_image_button')
     * @param {string} setLabel       - Text shown on the upload link    (e.g. 'Upload thumbnail for ComicScout')
     * @param {string} removeLabel    - Text shown on the remove link    (e.g. 'Remove thumbnail for ComicScout')
     */
    function initImageMetabox(metaboxId, inputId, uploadBtnId, removeBtnId, setLabel, removeLabel) {
        var metabox   = $('#' + metaboxId);
        var fileFrame = null;

        /* Upload */
        metabox.on('click', '#' + uploadBtnId, function (e) {
            e.preventDefault();
            var button = $(this);

            if (fileFrame) {
                fileFrame.open();
                return;
            }

            fileFrame = wp.media({
                title:    button.data('uploader_title'),
                button:   { text: button.data('uploader_button_text') },
                multiple: false
            });

            fileFrame.on('select', function () {
                var attachment = fileFrame.state().get('selection').first().toJSON();

                $('#' + inputId).val(attachment.id);

                // Hide the "Image Preview" placeholder, show the actual preview img
                metabox.find('div[style*="aspect-ratio"]').hide();
                metabox.find('img')
                    .attr('src',    attachment.url)
                    .attr('srcset', attachment.url)
                    .show();

                metabox.find('#' + uploadBtnId)
                    .attr('id', removeBtnId)
                    .text(removeLabel);
            });

            fileFrame.open();
        });

        /* Remove */
        metabox.on('click', '#' + removeBtnId, function (e) {
            e.preventDefault();

            $('#' + inputId).val('');

            // Hide the preview img, restore the "Image Preview" placeholder
            metabox.find('img')
                .attr('src',    '')
                .attr('srcset', '')
                .hide();
            metabox.find('div[style*="aspect-ratio"]').show();

            $(this)
                .attr('id', uploadBtnId)
                .text(setLabel);

            fileFrame = null; // reset so a fresh frame opens next time
        });
    }

    /* --- Register each metabox --- */

    initImageMetabox(
        'comicscout-social-share-image-metabox',
        'comicscout_social_share_image',
        'upload_comicscout_social_share_image_button',
        'remove_comicscout_social_share_image_button',
        'Upload social share image for ComicScout',
        'Remove social share image for ComicScout'
    );

    initImageMetabox(
        'comicscout-image-metabox',
        'comicscout_image',
        'upload_comicscout_image_button',
        'remove_comicscout_image_button',
        'Upload thumbnail for ComicScout',
        'Remove thumbnail for ComicScout'
    );

    initImageMetabox(
        'series-hero-metabox',
        'series_hero_image',
        'upload_series_hero_image_button',
        'remove_series_hero_image_button',
        'Set series hero image',
        'Remove series hero image'
    );

    initImageMetabox(
        'series-mobile-hero-metabox',
        'series_mobile_hero_image',
        'upload_series_mobile_hero_image_button',
        'remove_series_mobile_hero_image_button',
        'Set mobile hero image',
        'Remove mobile hero image'
    );

    initImageMetabox(
        'series-bg-image-metabox',
        'series_bg_image',
        'upload_series_bg_image_button',
        'remove_series_bg_image_button',
        'Set series background image',
        'Remove series background image'
    );

});
	// ---------------------------
	// ,Manga hero images
	// ---------------------------
	jQuery(document).ready(function ($) {
		var file_frame;

		function uploadHeroImage(button) {
			var wrapper = button.closest('.hero-image-metabox');
			var field = wrapper.find('input[type=hidden]');
			var img = wrapper.find('img');

			if (file_frame) {
				file_frame.open();
				return;
			}

			file_frame = wp.media.frames.file_frame = wp.media({
				title: button.data('uploader_title') || 'Choose an image',
				button: { text: button.data('uploader_button_text') || 'Set image' },
				multiple: false
			});

			file_frame.on('select', function () {
				var attachment = file_frame.state().get('selection').first().toJSON();
				field.val(attachment.id);
				img.attr('src', attachment.url).show();
				wrapper.find('.remove-hero-image').show();
			});

			file_frame.open();
		}

		$(document).on('click', '.upload-hero-image', function (e) {
			e.preventDefault();
			uploadHeroImage($(this));
		});

		$(document).on('click', '.remove-hero-image', function (e) {
			e.preventDefault();
			var wrapper = $(this).closest('.hero-image-metabox');
			wrapper.find('input[type=hidden]').val('');
			wrapper.find('img').hide().attr('src', '');
			$(this).hide();
		});
	});

	/* Manga Chapter Images */

	// ---------------------------
	// Gallery image uploader
	// ---------------------------
	$(document.body).on('click', '.manga-page-upload-button', function (event) {
		event.preventDefault();

		const button = $(this);
		const hiddenField = button.prev('input[type=hidden]');
		let hiddenFieldValueArray = hiddenField.val() ? hiddenField.val().split(',').map(Number) : [];

		const customUploader = wp.media({
			title: 'Insert Chapter Pages',
			library: { type: 'image' },
			button: { text: 'Use these images' },
			multiple: true
		}).on('select', function () {
			let selectedImages = customUploader.state().get('selection').map(item => item.toJSON());

			selectedImages.forEach(image => {
				const li = $('<li>', { 'data-id': image.id });
				const span = $('<span>', {
					class: 'manga-chapter-page',
					css: { 'background-image': 'url(' + image.url + ')' }
				});
				const removeBtn = $('<a>', {
					class: 'button manga-chapter-gallery-remove',
					'data-hidden': hiddenField.attr('name'),
					'data-id': image.id,
					href: '#',
					text: 'Remove'
				});

				li.append(span).append(removeBtn);
				$('.manga-chapter-gallery').append(li);

				hiddenFieldValueArray.push(image.id);
			});

			hiddenField.val(hiddenFieldValueArray.join(','));

			// Refresh sortable
			$('.manga-chapter-gallery').sortable('refresh');
		}).open();
	});

	// ---------------------------
	// Remove image event
	// ---------------------------
	$(document.body).on('click', '.manga-chapter-gallery-remove', function (event) {
		event.preventDefault();

		const button = $(this);
		const imageId = parseInt(button.data('id'));
		const hiddenField = $('input:hidden[name="' + button.data('hidden') + '"]');
		let hiddenFieldValueArray = hiddenField.val() ? hiddenField.val().split(',').map(Number) : [];

		button.parent().remove(); // remove <li>

		// Remove from hidden field
		hiddenFieldValueArray = hiddenFieldValueArray.filter(id => id !== imageId);
		hiddenField.val(hiddenFieldValueArray.join(','));
	});

	// ---------------------------
	// Drag & Drop Sorting
	// ---------------------------
	$('.manga-chapter-gallery').sortable({
		items: 'li',
		cursor: '-webkit-grabbing',
		scrollSensitivity: 40,
		stop: function () {
			const hiddenField = $(this).siblings('input[type=hidden]'); // assumes hidden input is sibling
			const sort = $(this).children('li').map(function () {
				return $(this).data('id');
			}).get();

			hiddenField.val(sort.join(','));
		}
	});




});
