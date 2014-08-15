describe("Installation", function() {
	describe("test", function() {
		it("Must hide host, username and password if SQLite is choosen", function() {
			var fields_to_show_hide = 'input[name="dbhost"],input[name="dbuser"],input[name="dbpasswd"]';
			loadFixtures('install.html');

			expect($('.no-sqlite,'+fields_to_show_hide)).toBeVisible();
			$('#dblayer option[value=sqlite]').attr('selected', 'selected');
			$('#dblayer').change();
			expect($('.no-sqlite,'+fields_to_show_hide)).toBeHidden();
		});
		it("Must show host, username and password again if something else is choosen after SQLite", function() {
			var fields_to_show_hide = 'input[name="dbhost"],input[name="dbuser"],input[name="dbpasswd"]';
			loadFixtures('install.html');

			expect($('.no-sqlite,'+fields_to_show_hide)).toBeVisible();
			$('#dblayer option[value=sqlite]').attr('selected', 'selected');
			$('#dblayer').change();
			expect($('.no-sqlite,'+fields_to_show_hide)).toBeHidden();
			$('#dblayer option[value=mysql]').attr('selected', 'selected');
			$('#dblayer').change();
			expect($('.no-sqlite,'+fields_to_show_hide)).toBeVisible();
		});
	});
});
