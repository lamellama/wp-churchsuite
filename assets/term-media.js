window.addEventListener('load', function () {
	const frameSettings = {
		title: 'Select image',
		button: { text: 'Use image' },
		multiple: false,
	};

	const mediaFieldSelector = '#churchsuite_category_image_id';
	const uploadButtonSelector = '.churchsuite-category-image-upload';
	const removeButtonSelector = '.churchsuite-category-image-remove';
	const previewSelector = '.churchsuite-category-image-preview';

	function handleUploadClick(event) {
		event.preventDefault();
		const button = event.currentTarget;
		const container = button.closest('div, td, .form-field, .term-group-wrap') || document;
		const field = container.querySelector(mediaFieldSelector);
		const preview = container.querySelector(previewSelector);
		const removeButton = container.querySelector(removeButtonSelector);

		const frame = wp.media(frameSettings);
		frame.on('select', function () {
			const attachment = frame.state().get('selection').first().toJSON();
			if (!field || !attachment || !attachment.id) {
				return;
			}
			field.value = attachment.id;
			if (preview) {
				preview.innerHTML = '<img src="' + attachment.url + '" style="max-width:150px;height:auto;" />';
			}
			if (removeButton) {
				removeButton.style.display = '';
			}
		});
		frame.open();
	}

	function handleRemoveClick(event) {
		event.preventDefault();
		const button = event.currentTarget;
		const container = button.closest('div, td, .form-field, .term-group-wrap') || document;
		const field = container.querySelector(mediaFieldSelector);
		const preview = container.querySelector(previewSelector);
		button.style.display = 'none';
		if (field) {
			field.value = '';
		}
		if (preview) {
			preview.innerHTML = '';
		}
	}

	document.querySelectorAll(uploadButtonSelector).forEach((btn) => {
		btn.addEventListener('click', handleUploadClick);
	});
	document.querySelectorAll(removeButtonSelector).forEach((btn) => {
		btn.addEventListener('click', handleRemoveClick);
	});
});
