import './jquery.ajaxmanager';

function add_thumbnail_to_queue(queue, img, loop) {
    queue.add({
	type: 'GET',
	url: img.data('src'),
	data: { ajaxload: 'true' },
	dataType: 'json',
	beforeSend: function(){$('.loader').show()},
	success: function(result) {
	    img.attr('src', result.url);
	    $('.loader').hide();
	},
	error: function() {
	    if (loop < 3)
		add_thumbnail_to_queue(queue, img, ++loop); // Retry 3 times
	    if (typeof( error_icon ) != "undefined") {
		img.attr('src', error_icon);
	    }
	    $('.loader').hide();
	}
    });
}

$(function() {
    let max_requests = max_requests || 3;

    let thumbnails_queue = $.manageAjax.create('queued', {
	queue: true,
	cacheResponse: false,
	maxRequests: max_requests,
	preventDoubleRequests: false
    });

    $('img[data-src]').each(function() {
	add_thumbnail_to_queue(thumbnails_queue, $(this), 0);
    });
});
