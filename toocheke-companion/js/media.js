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

    var guide = $('#toocheke-featured-image-ratio-guide');
    if (!guide.length) return;

    function toggleGuide() {
        // If WP has a featured image set, the #postimagediv contains an <img>
        var hasImage = $('#postimagediv .inside img').length > 0;
        guide.toggle(!hasImage);
    }

    // Run on page load
    toggleGuide();

    // WP updates featured image via AJAX; watch for changes
    var target = document.querySelector('#postimagediv .inside');
    if (target) {
        var observer = new MutationObserver(function () {
            toggleGuide();
        });
        observer.observe(target, { childList: true, subtree: true });
    }

    // Also hook clicks to catch remove/set actions
    $(document).on('click', '#postimagediv a', function () {
        setTimeout(toggleGuide, 300);
    });

});
var comicscout_image_file_frame;

jQuery.fn.upload_comicscout_image = function (button) {

	// If the media frame already exists, reopen it.
	if (comicscout_image_file_frame) {
		comicscout_image_file_frame.open();
		return;
	}

	comicscout_image_file_frame = wp.media.frames.comicscout_image_file_frame = wp.media({
		title: button.data('uploader_title'),
		button: {
			text: button.data('uploader_button_text'),
		},
		multiple: false
	});

	comicscout_image_file_frame.on('select', function () {

		var attachment = comicscout_image_file_frame.state().get('selection').first().toJSON();

		jQuery("#comicscout_image").val(attachment.id);

		jQuery("#comicscout-image-metabox img")
			.attr('src', attachment.url)
			.attr('srcset', attachment.url)
			.show();

		jQuery('#upload_comicscout_image_button')
			.attr('id', 'remove_comicscout_image_button')
			.text('Remove thumbnail for ComicScout');

	});

	comicscout_image_file_frame.open();
};

jQuery('#comicscout-image-metabox').on('click', '#upload_comicscout_image_button', function (event) {
	event.preventDefault();
	jQuery.fn.upload_comicscout_image(jQuery(this));
});

jQuery('#comicscout-image-metabox').on('click', '#remove_comicscout_image_button', function (event) {

	event.preventDefault();

	jQuery('#comicscout_image').val('');

	jQuery('#comicscout-image-metabox img')
		.attr('src', '')
		.attr('srcset', '')
		.hide();

	jQuery(this)
		.attr('id', 'upload_comicscout_image_button')
		.text('Upload thumbnail for ComicScout');

});


	/* Series Hero Image */
// Uploading files
// Desktop
var series_hero_desktop_file_frame;

jQuery.fn.upload_series_hero_image = function (button) {

	// If the media frame already exists, reopen it.
	if (series_hero_desktop_file_frame) {
		series_hero_desktop_file_frame.open();
		return;
	}

	series_hero_desktop_file_frame = wp.media.frames.series_hero_desktop_file_frame = wp.media({
		title: button.data('uploader_title'),
		button: {
			text: button.data('uploader_button_text'),
		},
		multiple: false
	});

	series_hero_desktop_file_frame.on('select', function () {

		var attachment = series_hero_desktop_file_frame.state().get('selection').first().toJSON();

		jQuery("#series_hero_image").val(attachment.id);

		jQuery("#series-hero-metabox img")
			.attr('src', attachment.url)
			.attr('srcset', attachment.url)
			.show();

		jQuery('#upload_series_hero_image_button')
			.attr('id', 'remove_series_hero_image_button')
			.text('Remove series hero image');

	});

	series_hero_desktop_file_frame.open();
};

jQuery('#series-hero-metabox').on('click', '#upload_series_hero_image_button', function (event) {
	event.preventDefault();
	jQuery.fn.upload_series_hero_image(jQuery(this));
});

jQuery('#series-hero-metabox').on('click', '#remove_series_hero_image_button', function (event) {

	event.preventDefault();

	jQuery('#series_hero_image').val('');

	jQuery('#series-hero-metabox img')
		.attr('src', '')
		.attr('srcset', '')
		.hide();

	jQuery(this)
		.attr('id', 'upload_series_hero_image_button')
		.text('Set series hero image');

});



/* Mobile Hero */

var series_hero_mobile_file_frame;

jQuery.fn.upload_series_mobile_hero_image = function (button) {

	if (series_hero_mobile_file_frame) {
		series_hero_mobile_file_frame.open();
		return;
	}

	series_hero_mobile_file_frame = wp.media.frames.series_hero_mobile_file_frame = wp.media({
		title: button.data('uploader_title'),
		button: {
			text: button.data('uploader_button_text'),
		},
		multiple: false
	});

	series_hero_mobile_file_frame.on('select', function () {

		var attachment = series_hero_mobile_file_frame.state().get('selection').first().toJSON();

		jQuery("#series_mobile_hero_image").val(attachment.id);

		jQuery("#series-mobile-hero-metabox img")
			.attr('src', attachment.url)
			.attr('srcset', attachment.url)
			.show();

		jQuery('#upload_series_mobile_hero_image_button')
			.attr('id', 'remove_series_mobile_hero_image_button')
			.text('Remove series hero image');

	});

	series_hero_mobile_file_frame.open();
};

jQuery('#series-mobile-hero-metabox').on('click', '#upload_series_mobile_hero_image_button', function (event) {
	event.preventDefault();
	jQuery.fn.upload_series_mobile_hero_image(jQuery(this));
});

jQuery('#series-mobile-hero-metabox').on('click', '#remove_series_mobile_hero_image_button', function (event) {

	event.preventDefault();

	jQuery('#series_mobile_hero_image').val('');

	jQuery('#series-mobile-hero-metabox img')
		.attr('src', '')
		.attr('srcset', '')
		.hide();

	jQuery(this)
		.attr('id', 'upload_series_mobile_hero_image_button')
		.text('Set series hero image');

});



/* Background Image */

var series_bg_file_frame;

jQuery.fn.upload_series_bg_image = function (button) {

	if (series_bg_file_frame) {
		series_bg_file_frame.open();
		return;
	}

	series_bg_file_frame = wp.media.frames.series_bg_file_frame = wp.media({
		title: button.data('uploader_title'),
		button: {
			text: button.data('uploader_button_text'),
		},
		multiple: false
	});

	series_bg_file_frame.on('select', function () {

		var attachment = series_bg_file_frame.state().get('selection').first().toJSON();

		jQuery("#series_bg_image").val(attachment.id);

		jQuery("#series-bg-image-metabox img")
			.attr('src', attachment.url)
			.attr('srcset', attachment.url)
			.show();

		jQuery('#upload_series_bg_image_button')
			.attr('id', 'remove_series_bg_image_button')
			.text('Remove series background image');

	});

	series_bg_file_frame.open();
};

jQuery('#series-bg-image-metabox').on('click', '#upload_series_bg_image_button', function (event) {
	event.preventDefault();
	jQuery.fn.upload_series_bg_image(jQuery(this));
});

jQuery('#series-bg-image-metabox').on('click', '#remove_series_bg_image_button', function (event) {

	event.preventDefault();

	jQuery('#series_bg_image').val('');

	jQuery('#series-bg-image-metabox img')
		.attr('src', '')
		.attr('srcset', '')
		.hide();

	jQuery(this)
		.attr('id', 'upload_series_bg_image_button')
		.text('Set series background image');

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
