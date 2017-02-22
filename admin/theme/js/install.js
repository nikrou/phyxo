$(function() {
	$option_selected = $('#dblayer option:selected').attr('value');

	if ($option_selected=='sqlite') {
		$('.no-sqlite').hide();
	}

	$(document).on('change', '#dblayer', function() {
		if (this.value=='sqlite') {
			$('.no-sqlite').hide();
		} else {
			$('.no-sqlite').show();
		}
	});
});
