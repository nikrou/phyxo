$(function() {
	var targets = {
		'input[name="rate"]' : '#rate_anonymous',
		'input[name="allow_user_registration"]' : '#email_admin_on_new_user',
		'input[name="comments_validation"]' : '#email_admin_on_comment_validation',
		'input[name="user_can_edit_comment"]' : '#email_admin_on_comment_edition',
		'input[name="user_can_delete_comment"]' : '#email_admin_on_comment_deletion',
	};

	for (selector in targets) {
		var target = targets[selector];

		$(target).toggle($(selector).is(':checked'));

		(function(target){
			$(selector).on('change', function() {
				$(target).toggle($(this).is(':checked'));
			});
		})(target);
	};
});
