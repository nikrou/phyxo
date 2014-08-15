$(function(){
	function highlighComments() {
		$(".checkComment").each(function() {
			var parent = $(this).parent('tr');
			if ($(this).children("input[type=checkbox]").is(':checked')) {
				$(parent).addClass('selectedComment');
			}
			else {
				$(parent).removeClass('selectedComment');
			}
		});
	}

	$(".checkComment").click(function(event) {
		var checkbox = $(this).children("input[type=checkbox]");
		if (event.target.type !== 'checkbox') {
			$(checkbox).prop('checked', !$(checkbox).prop('checked'));
		}
		highlighComments();
	});

	$("#commentSelectAll").click(function () {
		$(".checkComment input[type=checkbox]").prop('checked', true);
		highlighComments();
		return false;
	});

	$("#commentSelectNone").click(function () {
		$(".checkComment input[type=checkbox]").prop('checked', false);
		highlighComments();
		return false;
	});

	$("#commentSelectInvert").click(function () {
		$(".checkComment input[type=checkbox]").each(function() {
			$(this).prop('checked', !$(this).prop('checked'));
		});
		highlighComments();
		return false;
	});

});
