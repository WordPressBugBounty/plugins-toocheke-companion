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

	/* Series Hero Image */
	// Uploading files
	//Desktop
	var series_hero_desktop_file_frame;

	jQuery.fn.upload_series_hero_image = function (button) {
		var button_id = button.attr('id');
		var field_id = button_id.replace('_button', '');

		// If the media frame already exists, reopen it.
		if (series_hero_desktop_file_frame) {
			series_hero_desktop_file_frame.open();
			return;
		}

		// Create the media frame.
		series_hero_desktop_file_frame = wp.media.frames.series_hero_desktop_file_frame = wp.media({
			title: jQuery(this).data('uploader_title'),
			button: {
				text: jQuery(this).data('uploader_button_text'),
			},
			multiple: false
		});

		// When an image is selected, run a callback.
		series_hero_desktop_file_frame.on('select', function () {
			jQuery('#series-hero-metabox img').attr('src', '');
			var attachment = series_hero_desktop_file_frame.state().get('selection').first().toJSON();
			jQuery("#" + field_id).val(attachment.id);
			jQuery("#series-hero-metabox img").attr('src', attachment.url);
			jQuery("#series-hero-metabox img").attr('srcset', attachment.url);
			jQuery('#series-hero-metabox img').show();
			jQuery('#' + button_id).attr('id', 'remove_series_hero_image_button');
			jQuery('#remove_series_hero_image_button').text('Remove series hero image');
		});

		// Finally, open the modal
		series_hero_desktop_file_frame.open();
	};

	jQuery('#series-hero-metabox').on('click', '#upload_series_hero_image_button', function (event) {
		event.preventDefault();
		jQuery.fn.upload_series_hero_image(jQuery(this));
	});

	jQuery('#series-hero-metabox').on('click', '#remove_series_hero_image_button', function (event) {
		event.preventDefault();
		jQuery('#upload_series_hero_image').val('');
		jQuery('#series-hero-metabox img').attr('src', '');
		jQuery("#series-hero-metabox img").attr('srcset', '');
		jQuery('#series-hero-metabox img').hide();
		jQuery(this).attr('id', 'upload_series_hero_image_button');
		jQuery('#upload_series_hero_image_button').text('Set series hero image');
	});

	//Mobile
	var series_hero_mobile_file_frame;

	jQuery.fn.upload_series_mobile_hero_image = function (button) {
		var button_id = button.attr('id');
		var field_id = button_id.replace('_button', '');

		// If the media frame already exists, reopen it.
		if (series_hero_mobile_file_frame) {
			series_hero_mobile_file_frame.open();
			return;
		}

		// Create the media frame.
		series_hero_mobile_file_frame = wp.media.frames.series_hero_mobile_file_frame = wp.media({
			title: jQuery(this).data('uploader_title'),
			button: {
				text: jQuery(this).data('uploader_button_text'),
			},
			multiple: false
		});

		// When an image is selected, run a callback.
		series_hero_mobile_file_frame.on('select', function () {
			var attachment = series_hero_mobile_file_frame.state().get('selection').first().toJSON();
			jQuery("#" + field_id).val(attachment.id);
			jQuery("#series-mobile-hero-metabox img").attr('src', attachment.url);
			jQuery("#series-mobile-hero-metabox img").attr('srcset', attachment.url);
			jQuery('#series-mobile-hero-metabox img').show();
			jQuery('#' + button_id).attr('id', 'emove_series_mobile_hero_image_button');
			jQuery('#emove_series_mobile_hero_image_button').text('Remove series hero image');
		});

		// Finally, open the modal
		series_hero_mobile_file_frame.open();
	};

	jQuery('#series-mobile-hero-metabox').on('click', '#upload_series_mobile_hero_image_button', function (event) {
		event.preventDefault();
		jQuery.fn.upload_series_mobile_hero_image(jQuery(this));
	});

	jQuery('#series-mobile-hero-metabox').on('click', '#remove_series_mobile_hero_image_button', function (event) {
		event.preventDefault();
		jQuery('#upload_series_mobile_hero_image').val('');
		jQuery('#series-mobile-hero-metabox img').attr('src', '');
		jQuery("#series-mobile-hero-metabox img").attr('srcset', '');
		jQuery('#series-mobile-hero-metabox img').hide();
		jQuery(this).attr('id', 'upload_series_mobile_hero_image_button');
		jQuery('#upload_series_mobile_hero_image_button').text('Set series hero image');
	});
	//Background
	var series_bg_file_frame;

	jQuery.fn.upload_series_bg_image = function (button) {
		var button_id = button.attr('id');
		var field_id = button_id.replace('_button', '');

		// If the media frame already exists, reopen it.
		if (series_bg_file_frame) {
			series_bg_file_frame.open();
			return;
		}

		// Create the media frame.
		series_bg_file_frame = wp.media.frames.series_bg_file_frame = wp.media({
			title: jQuery(this).data('uploader_title'),
			button: {
				text: jQuery(this).data('uploader_button_text'),
			},
			multiple: false
		});

		// When an image is selected, run a callback.
		series_bg_file_frame.on('select', function () {
			var attachment = series_bg_file_frame.state().get('selection').first().toJSON();
			jQuery("#" + field_id).val(attachment.id);
			jQuery("#series-bg-image-metabox img").attr('src', attachment.url);
			jQuery("#series-bg-image-metabox img").attr('srcset', attachment.url);
			jQuery('#series-bg-image-metabox img').show();
			jQuery('#' + button_id).attr('id', 'remove_series_bg_image_button');
			jQuery('#remove_series_bg_image_button').text('Remove series background image image');
		});

		// Finally, open the modal
		series_bg_file_frame.open();
	};

	jQuery('#series-bg-image-metabox').on('click', '#upload_series_bg_image_button', function (event) {
		event.preventDefault();
		jQuery.fn.upload_series_bg_image(jQuery(this));
	});

	jQuery('#series-bg-image-metabox').on('click', '#remove_series_bg_image_button', function (event) {
		event.preventDefault();
		jQuery('#upload_series_bg_image').val('');
		jQuery('#series-bg-image-metabox img').attr('src', '');
		jQuery("#series-bg-image-metabox img").attr('srcset', '');
		jQuery('#series-bg-image-metabox img').hide();
		jQuery(this).attr('id', 'upload_series_bg_image_button');
		jQuery('#upload_series_bg_image_button').text('Set series background image');
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
