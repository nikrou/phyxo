(function($) {
    'use strict';
    $.fn.lightAccordion = function(options) {
	console.log('lightAccordion', options);

	var settings = $.extend({
	    header: 'dt',
	    content: 'dd',
	    active: 0
	}, options);

	return this.each(function() {
	    var self = $(this);

	    var contents = self.find(settings.content),
		headers = self.find(settings.header);

	    contents.not(contents[settings.active]).hide();

	    self.on('click', settings.header, function() {
		var content = $(this).next(settings.content);
		content.slideDown();
				contents.not(content).slideUp();
	    });
	});
    };
})(jQuery);
