describe("Manage group of checkboxes", function() {
	it("Check one checkbox must highlight corresponding line (tr)", function() {
		loadFixtures('checkboxes.html');
		phyxo.checkboxesHelper('#tags-form');

		$('.checkboxes .check:eq(0)').click();
		expect($('.checkboxes .check:eq(0) input[type="checkbox"]')).toBeChecked();
		expect($('.checkboxes .check:eq(0)').parent()).toHaveClass('selected');
	});
	it("Check one checkbox and check it another time must restore initial state", function() {
		loadFixtures('checkboxes.html');
		phyxo.checkboxesHelper('#tags-form');

		expect($('.checkboxes .check:eq(0) input[type="checkbox"]')).not.toBeChecked();
		expect($('.checkboxes .check:eq(0)').parent()).not.toHaveClass('selected');
		$('.checkboxes .check:eq(0)').click();
		expect($('.checkboxes .check:eq(0) input[type="checkbox"]')).toBeChecked();
		expect($('.checkboxes .check:eq(0)').parent()).toHaveClass('selected');
		$('.checkboxes .check:eq(0)').click();
		expect($('.checkboxes .check:eq(0) input[type="checkbox"]')).not.toBeChecked();
		expect($('.checkboxes .check:eq(0)').parent()).not.toHaveClass('selected');
	});
	it("Click \"All\" must select all checkboxes", function() {
		loadFixtures('checkboxes.html');
		phyxo.checkboxesHelper('#tags-form');

		$('.checkboxes .check:eq(0)').click();
		$('.select.all').click();
		expect($('.checkboxes .check input[type="checkbox"]:checked').length).toBe(4);
		expect($('.checkboxes tr.selected .check input[type="checkbox"]:checked').length).toBe(4);
	});
	it("Click \"None\" must uncheck all checkboxes", function() {
		loadFixtures('checkboxes.html');
		phyxo.checkboxesHelper('#tags-form');

		$('.checkboxes .check:eq(0)').click();
		$('.checkboxes .check:eq(2)').click();
		$('.select.none').click();
		expect($('.checkboxes .check input[type="checkbox"]:checked').length).toBe(0);
		expect($('.checkboxes tr.selected .check input[type="checkbox"]:checked').length).toBe(0);
	});
	it("Click \"Invert\" must invert state of all checkboxes", function() {
		loadFixtures('checkboxes.html');
		phyxo.checkboxesHelper('#tags-form');

		$('.checkboxes .check:eq(0)').click();
		$('.checkboxes .check:eq(2)').click();
		$('.select.invert').click();
		expect($('.checkboxes .check input[type="checkbox"]:checked').length).toBe(2);
		expect($('.checkboxes tr.selected .check input[type="checkbox"]:checked').length).toBe(2);
		expect($('.checkboxes .check:eq(0) input[type="checkbox"]')).not.toBeChecked();
		expect($('.checkboxes .check:eq(1) input[type="checkbox"]')).toBeChecked();
		expect($('.checkboxes .check:eq(2) input[type="checkbox"]')).not.toBeChecked();
		expect($('.checkboxes .check:eq(3) input[type="checkbox"]')).toBeChecked();
	});
	it("Must take in consideration checkbox added dynamically", function() {
		loadFixtures("checkboxes.html");
		phyxo.checkboxesHelper('#tags-form');

		var tr = '<tr><td class="check"><input type="checkbox" name="tag_ids[]" value="5"/></td>';
		tr += '<td>5</td><td>&nsbp;</td><td></td></td></tr>';
		$(tr).appendTo('#tags-form table tbody');

		$('.select.all').click();
		expect($('.checkboxes .check input[type="checkbox"]:checked').length).toBe(5);
	});
});
