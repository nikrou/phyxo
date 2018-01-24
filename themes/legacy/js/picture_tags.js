(function($, undefined) {
	$.flashMessage = function(source, data) {
		$(source).html('');
		$(source).hide().append(data).fadeIn(500).fadeOut(3000);
	};
	$.fn.flashMessage = function(data) {
		this.each(function() {
			new $.flashMessage(this, data);
		});
	};

	var changeSubmitStatus = function changeSubmitStatus(status) {
		if (status=='disabled') {
			$('#user-tags-update')
				.prop('disabled', true)
				.addClass('disabled')
				.css('cursor', 'default');
		} else {
			$('#user-tags-update')
				.prop('disabled', false)
				.removeClass('disabled')
				.css('cursor', 'pointer');
		}
	};

	var formResponse = function formResponse(data, textStatus) {
		var message = '';
		changeSubmitStatus('disabled');
		if (data.stat=='ok') {
		    message = '<div class="infos">' + user_tags.tags_updated + '</div>';
		    $('#user-tags-update').hide();
		} else {
			message = '<div class="errors">' + data.message + '</div>';
		}
		if (message !== '') {
			$('.flash-messages').flashMessage(message);
		}
	};

	if ($('#user-tags').length==0) {
		return;
	}

	$.user_tags_target = $('#user-tags');
	$.user_tags_target.after('<div class="flash-messages" style="position: absolute;"></div>');
	changeSubmitStatus('disabled');

	$('.edit-tags').click(function() {
		$.user_tags_target.parent().toggleClass('js-hidden');
	});

	$(function() {
		var selectize_options = {
			delimiter: ',',
			persist: false,
			valueField: 'id',
			labelField: 'name',
			searchField: 'name',
			load: function(query, callback) {
				if (!query.length) { return callback;}
				$.getJSON(user_tags.ws_getList,
					  'q='+query,
					  function(data) {
						  for (var i=0;i<data.result.tags.length;i++) {
							  data.result.tags[i]['id'] = '~~'+data.result.tags[i]['id']+'~~';
						  };
						  callback(data.result.tags);
					  });
			},
			onChange: function() {
				changeSubmitStatus('enabled');
			}
		};

		if (user_tags.allow_delete) {
			selectize_options.plugins = ['remove_button'];
		}
		if (user_tags.allow_creation) {
			selectize_options.create = true;
		}
		$.user_tags_target.selectize(selectize_options);
	});

	$('#user-tags-form').submit(function(e) {
		var serialized_form = $(this).serialize();
		serialized_form = serialized_form.replace(/user_tags/g, 'tags');

		$.post($(this).attr('action'),
		       serialized_form,
		       function (data, textStatus) {
			       formResponse(data, textStatus);
		       },
		       'json'
		      ).fail(function(data, textStatus) {
			      formResponse(data.responseJSON, textStatus);
		      });
		e.preventDefault();
	});
})(jQuery);
