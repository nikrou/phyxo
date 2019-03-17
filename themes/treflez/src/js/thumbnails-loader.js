import Queue from './queue';

$(function () {
	const queue = new Queue();

	const fetchImage = (image) => {
		fetch(image.data('src')).then(response => {
			image.attr('src', response.url);
		}).catch(error => {
			if (error_icon !== undefined) {
				image.attr('src', error_icon)
			}
		})
	}

	$('img[data-src]').each(function () {
		queue.add(fetchImage($(this)));
	});
});
