$(function() {
	var fields_to_show_hide = 'input[name="dbhost"],input[name="dbuser"],input[name="dbpasswd"]';
	$option_selected = $('#dblayer option:selected').attr('value');

	if ($option_selected=='sqlite') {
		$(fields_to_show_hide).parent().parent().hide();
	}

	$('#dblayer').change(function() {
		$db = this;
		if ($db.value=='sqlite') {
			$(fields_to_show_hide).parent().parent().hide();
		} else {
			$(fields_to_show_hide).parent().parent().show();
		}
	});
});
