var phyxo = phyxo || {};
phyxo.checkboxesHelper = function checkboxesHelper(form) {
	$('.checkboxes .check', form).on('click', function(e) {
		var checkbox = $(this).children('input[type="checkbox"]');
		if (e.target.type !== 'checkbox') {
			checkbox.prop('checked', !$(checkbox).prop('checked'));
		}
		$(this).parent('tr').toggleClass('selected');
	});

	$('.select.all', form).click(function () {
		$('.checkboxes .check input[type="checkbox"]', form).not(':checked').trigger('click');
		return false;
	});

	$('.select.none', form).click(function () {
		$('.checkboxes .check input[type="checkbox"]:checked', form).trigger('click');
		return false;
	});

	$('.select.invert', form).click(function () {
		$('.checkboxes .check input[type="checkbox"]', form).each(function() {
			$(this).prop('checked', !$(this).prop('checked'));
			$(this).parents('tr').toggleClass('selected');
		});
		return false;
	});
};
