$(function() {
	/**
	 * Add user
	 */
	$("#addUser").click(function() {
		$("#addUserForm").toggle();
		$("#showAddUser .infos").hide();
		$("input[name=username]").focus();
		return false;
	});

	$("#addUserClose").click(function() {
		$("#addUserForm").hide();
		return false;
	});

	$("#addUserForm").submit(function() {
		$.ajax({
			url: "../ws.php?format=json&method=pwg.users.add",
			type:"POST",
			data: $(this).serialize()+"&pwg_token="+pwg_token,
			beforeSend: function() {
				$("#addUserForm .errors").hide();

				if ($("input[name=username]").val() == "") {
					$("#addUserForm .errors").html('&#x2718; '+missingUsername).show();
					return false;
				}

				$("#addUserForm .loading").show();
			},
			success:function(data) {
				oTable.fnDraw();
				$("#addUserForm .loading").hide();

				var data = $.parseJSON(data);
				if (data.stat == 'ok') {
					$("#addUserForm input[type=text], #addUserForm input[type=password]").val("");

					var new_user = data.result.users[0];
					allUsers.push(parseInt(new_user.id));
					$("#showAddUser .infos").html(sprintf(newUser_pattern, new_user.username)).show();
					checkSelection();

					$("#addUserForm").hide();
				}
				else {
					$("#addUserForm .errors").html('&#x2718; '+data.message).show();
				}
			},
			error:function(XMLHttpRequest, textStatus, errorThrows) {
				$("#addUserForm .loading").hide();
			}
		});

		return false;
	});

	/**
	 * Table with users
	 */
	/**
	 * find the key from a value in the startStopValues array
	 */
	function getSliderKeyFromValue(value, values) {
		for (var key in values) {
			if (values[key] >= value) {
				return key;
			}
		}
		return 0;
	}

	var recent_period_values = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,25,30,40,50,60,80,99];

	function getRecentPeriodInfoFromIdx(idx) {
		return phyxo_msg.days.replace('%d', recent_period_values[idx]);
	}

	var nb_image_page_values = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,35,40,45,50,60,70,80,90,100,200,300,500,999];

	function getNbImagePageInfoFromIdx(idx) {
		return phyxo_msg.photos_per_page.replace('%d', nb_image_page_values[idx]);
	}

	/* nb_image_page slider */
	var nb_image_page_init = getSliderKeyFromValue($('#action_nb_image_page input[name=nb_image_page]').val(), nb_image_page_values);

	$('#action_nb_image_page .nb_image_page_infos').html(getNbImagePageInfoFromIdx(nb_image_page_init));

	$('#action_nb_image_page .nb_image_page').slider({
		range: "min",
		min: 0,
		max: nb_image_page_values.length - 1,
		value: nb_image_page_init,
		slide: function( event, ui ) {
			$('#action_nb_image_page .nb_image_page_infos').html(getNbImagePageInfoFromIdx(ui.value));
		},
		stop: function( event, ui ) {
			$('#action_nb_image_page input[name=nb_image_page]').val(nb_image_page_values[ui.value]).trigger('change');
		}
	});

	/* recent_period slider */
	var recent_period_init = getSliderKeyFromValue($('#action_recent_period input[name=recent_period]').val(), recent_period_values);
	$('#action_recent_period .recent_period_infos').html(getRecentPeriodInfoFromIdx(recent_period_init));

	$('#action_recent_period .recent_period').slider({
		range: "min",
		min: 0,
		max: recent_period_values.length - 1,
		value: recent_period_init,
		slide: function( event, ui ) {
			$('#action_recent_period .recent_period_infos').html(getRecentPeriodInfoFromIdx(ui.value));
		},
		stop: function( event, ui ) {
			$('#action_recent_period input[name=recent_period]').val(recent_period_values[ui.value]).trigger('change');
		}
	});

	/* Formating function for row details */
	function fnFormatDetails(oTable, nTr) {
		var userId = oTable.fnGetData(nTr)[0];
		var sOut = null;

		$.ajax({
			url: "../ws.php?format=json&method=pwg.users.getList",
			type:"POST",
			data: {
				user_id: userId,
				display: "all",
			},
			success:function(data) {
				$("#user"+userId+" .loading").hide();

				var data = $.parseJSON(data);
				if (data.stat == 'ok') {
					var user = data.result.users[0];

					/* Prepare data for template */
					user.statusOptions = [];
					$("#action select[name=status] option").each(function() {
						var option = {value:$(this).val(), label:$(this).html(), isSelected:false};

						if (user.status == $(this).val()) {
							option.isSelected = true;
						}

						user.statusOptions.push(option);
					});

					user.levelOptions = [];
					$("#action select[name=level] option").each(function() {
						var option = {value:$(this).val(), label:$(this).html(), isSelected:false};

						if (user.level == $(this).val()) {
							option.isSelected = true;
						}

						user.levelOptions.push(option);
					});

					user.groupOptions = [];
					$("#action select[name=associate] option").each(function() {
						var option = {value:$(this).val(), label:$(this).html(), isSelected:false};

						if (user.groups && user.groups.indexOf(parseInt($(this).val())) != -1) {
							option.isSelected = true;
						}

						user.groupOptions.push(option);
					});

					user.themeOptions = [];
					$("#action select[name=theme] option").each(function() {
						var option = {value:$(this).val(), label:$(this).html(), isSelected:false};

						if (user.theme == $(this).val()) {
							option.isSelected = true;
						}

						user.themeOptions.push(option);
					});

					user.languageOptions = [];
					$("#action select[name=language] option").each(function() {
						var option = {value:$(this).val(), label:$(this).html(), isSelected:false};

						if (user.language == $(this).val()) {
							option.isSelected = true;
						}

						user.languageOptions.push(option);
					});

					user.isGuest = (parseInt(userId) == guestUser);
					user.isProtected = (protectedUsers.indexOf(parseInt(userId)) != -1);

					user.registeredOn_string = sprintf(
						registeredOn_pattern,
						user.registration_date_string,
						user.registration_date_since
					);

					user.lastVisit_string = "";
					if (typeof user.last_visit != 'undefined') {
						user.lastVisit_string = sprintf(lastVisit_pattern, user.last_visit_string, user.last_visit_since);
					}

					user.updateString = phyxo_msg.user_updated.replace('%s', user.username);

					user.email = user.email || '';

					$("#action select[name=status] option").each(function() {
						if (user.status == $(this).val()) {
							user.statusLabel = $(this).html();
						}
					});

					/* Render the underscore template */
					_.templateSettings.variable = "user";

					var template = _.template(
						$("script.userDetails").html()
					);

					$("#user"+userId).append(template(user));

					/* groups select */
					$('[data-selectize=groups]').selectize({
						valueField: 'value',
						labelField: 'label',
						searchField: ['label'],
						plugins: ['remove_button']
					});

					var groupSelectize = $('[data-selectize=groups]')[0].selectize;

					groupSelectize.load(function(callback) {
						callback(user.groupOptions);
					});

					$.each($.grep(user.groupOptions, function(group) {
						return group.isSelected;
					}), function(i, group) {
						groupSelectize.addItem(group.value);
					});

					/* nb_image_page slider */
					var nb_image_page_init = getSliderKeyFromValue($('#user'+userId+' input[name=nb_image_page]').val(), nb_image_page_values);

					$('#user'+userId+' .nb_image_page_infos').html(getNbImagePageInfoFromIdx(nb_image_page_init));

					$('#user'+userId+' .nb_image_page').slider({
						range: "min",
						min: 0,
						max: nb_image_page_values.length - 1,
						value: nb_image_page_init,
						slide: function( event, ui ) {
							$('#user'+userId+' .nb_image_page_infos').html(getNbImagePageInfoFromIdx(ui.value));
						},
						stop: function( event, ui ) {
							$('#user'+userId+' input[name=nb_image_page]').val(nb_image_page_values[ui.value]).trigger('change');
						}
					});

					/* recent_period slider */
					var recent_period_init = getSliderKeyFromValue($('#user'+userId+' input[name=recent_period]').val(), recent_period_values);
					$('#user'+userId+' .recent_period_infos').html(getRecentPeriodInfoFromIdx(recent_period_init));

					$('#user'+userId+' .recent_period').slider({
						range: "min",
						min: 0,
						max: recent_period_values.length - 1,
						value: recent_period_init,
						slide: function( event, ui ) {
							$('#user'+userId+' .recent_period_infos').html(getRecentPeriodInfoFromIdx(ui.value));
						},
						stop: function( event, ui ) {
							$('#user'+userId+' input[name=recent_period]').val(recent_period_values[ui.value]).trigger('change');
						}
					});
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrows) {
			}
		});

		return '<div id="user'+userId+'" class="userProperties"><img class="loading" src="themes/default/images/ajax-loader-small.gif" alt=""></div>';
	}

	/* change password */
	$(document).on('click', '.changePasswordOpen',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();

		$(this).hide();
		$('#user'+userId+' .changePasswordDone').hide();
		$('#user'+userId+' .changePassword').show();
		$('#user'+userId+' .changePassword input[type=text]').focus();

		return false;
	});

	$(document).on('click', '.changePassword a.updatePassword',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();

		$('#user'+userId+' .changePassword a .text').hide();
		$('#user'+userId+' .changePassword a img').show();

		$.ajax({
			url: "../ws.php?format=json&method=pwg.users.setInfo",
			type:"POST",
			data: {
				pwg_token:pwg_token,
				user_id:userId,
				password: $('#user'+userId+' .changePassword input[type=text]').val()
			},
			beforeSend: function() {
				$('#user'+userId+' .changePassword input[type=text]').val("");
			},
			success:function(data) {
				$('#user'+userId+' .changePassword a .text').show();
				$('#user'+userId+' .changePassword a img').hide();
				$('#user'+userId+' .changePassword').hide();
				$('#user'+userId+' .changePasswordOpen').show();
				$('#user'+userId+' .changePasswordDone').show();
			},
			error:function(XMLHttpRequest, textStatus, errorThrows) {
			}
		});

		return false;
	});

	$(document).on('click', '.changePassword a.cancel',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();

		$('#user'+userId+' .changePassword').hide();
		$('#user'+userId+' .changePasswordOpen').show();

		return false;
	});

	/* change username */
	$(document).on('click', '.changeUsernameOpen a',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();
		var username = $('#user'+userId+' .username').html();

		$('#user'+userId+' .changeUsernameOpen').hide();
		$('#user'+userId+' .changeUsername').show();
		$('#user'+userId+' .changeUsername input[type=text]').val(username).focus();

		return false;
	});

	$(document).on('click', 'a.updateUsername',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();

		$('#user'+userId+' .changeUsername a .text').hide();
		$('#user'+userId+' .changeUsername a img').show();

		$.ajax({
			url: "../ws.php?format=json&method=pwg.users.setInfo",
			type:"POST",
			data: {
				pwg_token:pwg_token,
				user_id:userId,
				username: $('#user'+userId+' .changeUsername input[type=text]').val()
			},
			success:function(data) {
				$('#user'+userId+' .changeUsername a .text').show();
				$('#user'+userId+' .changeUsername a img').hide();
				$('#user'+userId+' .changeUsername').hide();
				$('#user'+userId+' .changeUsernameOpen').show();

				var data = $.parseJSON(data);
				$('#user'+userId+' .username').html(data.result.users[0].username);
			},
			error:function(XMLHttpRequest, textStatus, errorThrows) {
			}
		});

		return false;
	});

	$(document).on('click', '.changeUsername a.cancel',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();

		$('#user'+userId+' .changeUsername').hide();
		$('#user'+userId+' .changeUsernameOpen').show();

		return false;
	});

	/* display the "save" button when a field changes */
	$(document).on('change', '.userProperties input, .userProperties select',  function() {
		var userId = $(this).parentsUntil('form').parent().find('input[name=user_id]').val();

		$('#user'+userId+' input[type=submit]').show();
		$('#user'+userId+' .propertiesUpdateDone').hide();
	});

	/* delete user */
	$(document).on('click', '.userDelete a',  function() {
		if (!confirm(phyxo_msg.are_you_sure)) {
			return false;
		}

		var userId = $(this).data('user_id');
		var username = $('#user'+userId+' .username').html();

		$.ajax({
			url: "../ws.php?format=json&method=pwg.users.delete",
			type:"POST",
			data: {
				user_id:userId,
				pwg_token:pwg_token
			},
			beforeSend: function() {
				$('#user'+userId+' .userDelete .loading').show();
			},
			success:function(data) {
				oTable.fnDraw();
				$('#showAddUser .infos').html('&#x2714; User '+username+' deleted').show();
			},
			error:function(XMLHttpRequest, textStatus, errorThrows) {
				$('#user'+userId+' .userDelete .loading').hide();
			}
		});

		return false;
	});

	$(document).on('click', '.userProperties input[type=submit]',  function() {
		var userId = $(this).data('user_id');

		var formData = $('#user'+userId+' form').serialize();
		formData += '&pwg_token='+pwg_token;

		if ($('#user'+userId+' form select[name="group_id[]"] option:selected').length == 0) {
			formData += '&group_id=-1';
		}

		if (!$('#user'+userId+' form input[name=enabled_high]').is(':checked')) {
			formData += '&enabled_high=false';
		}

		if (!$('#user'+userId+' form input[name=expand]').is(':checked')) {
			formData += '&expand=false';
		}

		if (!$('#user'+userId+' form input[name=show_nb_hits]').is(':checked')) {
			formData += '&show_nb_hits=false';
		}

		if (!$('#user'+userId+' form input[name=show_nb_comments]').is(':checked')) {
			formData += '&show_nb_comments=false';
		}

		$.ajax({
			url: "../ws.php?format=json&method=pwg.users.setInfo",
			type:"POST",
			data: formData,
			beforeSend: function() {
				$('#user'+userId+' .submitWait').show();
			},
			success:function(data) {
				$('#user'+userId+' .submitWait').hide();
				$('#user'+userId+' input[type=submit]').hide();
				$('#user'+userId+' .propertiesUpdateDone').show();
			},
			error:function(XMLHttpRequest, textStatus, errorThrows) {
				$('#user'+userId+' .submitWait').hide();
			}
		});

		return false;
	});

	/* Add event listener for opening and closing details
	 * Note that the indicator for showing which row is open is not controlled by DataTables,
	 * rather it is done here
	 */
	$(document).on('click', '#userList tbody td .openUserDetails',  function() {
		var nTr = this.parentNode.parentNode;
		if ($(this).hasClass('icon-cancel-circled')) {
			/* This row is already open - close it */
			$(this)
				.removeClass('icon-cancel-circled')
				.addClass('icon-pencil')
				.attr('title', phyxo_msg.open_user_details)
				.html(phyxo_msg.edit);

			oTable.fnClose( nTr );
		}
		else {
			/* Open this row */
			$(this)
				.removeClass('icon-pencil')
				.addClass('icon-cancel-circled')
				.attr('title', phyxo_msg.close_user_details)
				.html(phyxo_msg.close);

			oTable.fnOpen( nTr, fnFormatDetails(oTable, nTr), 'details');
		}
	});


	/* first column must be prefixed with the open/close icon */
	var aoColumns = [
		{
			'bVisible':false
		},
		{
			"mRender": function(data, type, full) {
				return '<label><input type="checkbox" data-user_id="'+full[0]+'"> '
					+data+'</label> <a title="'+phyxo_msg.open_user_details+'" class="icon-pencil openUserDetails">'
					+phyxo_msg.edit+'</a>';
			}
		}
	];

	for (i=2; i<$("#userList thead tr th").length; i++) {
		aoColumns.push(null);
	}

	var oTable = $('#userList').dataTable({
		"iDisplayLength": 10,
		"bDeferRender": true,
		"bProcessing": true,
		"bServerSide": true,
		"sServerMethod": "POST",
		"sAjaxSource": "./user_list_backend.php",
		"oLanguage": {
			"sProcessing": phyxo_msg.loading,
			"sLengthMenu": phyxo_msg.show_users.replace('%s', '_MENU_'),
			"sZeroRecords": phyxo_msg.no_matching_user,
			"sInfo": phyxo_msg.showing_to_users.replace('%s', '_START_').replace('%s', '_END_').replace('%s', '_TOTAL_'),
			"sInfoEmpty": phyxo_msg.no_matching_user,
			"sInfoFiltered": phyxo_msg.filtered_from_total_users.replace('%s', '_MAX_'),
			"sSearch": '<span class="icon-search"></span>'+phyxo_msg.search,
			"sLoadingRecords": phyxo_msg.loading,
			"oPaginate": {
				"sFirst": phyxo_msg.first,
				"sPrevious": '← '+phyxo_msg.previous,
				"sNext": phyxo_msg.next+' →',
				"sLast": phyxo_msg.last
			}
		},
		"fnDrawCallback": function( oSettings ) {
			$("#userList input[type=checkbox]").each(function() {
				var user_id = $(this).data("user_id");
				$(this).prop('checked', (selection.indexOf(user_id) != -1));
			});
		},
		"aoColumns": aoColumns
	});

	/**
	 * Selection management
	 */
	function checkSelection() {
		if (selection.length > 0) {
			$("#forbidAction").hide();
			$("#permitAction").show();

			$("#applyOnDetails").text(
				sprintf(
					applyOnDetails_pattern,
					selection.length
				)
			);

			if (selection.length == allUsers.length) {
				$("#selectedMessage").text(
					sprintf(
						selectedMessage_all,
						allUsers.length
					)
				);
			}
			else {
				$("#selectedMessage").text(
					sprintf(
						selectedMessage_pattern,
						selection.length,
						allUsers.length
					)
				);
			}
		}
		else {
			$("#forbidAction").show();
			$("#permitAction").hide();

			$("#selectedMessage").text(
				sprintf(
					selectedMessage_none,
					allUsers.length
				)
			);
		}

		$("#applyActionBlock .infos").hide();
	}

	$(document).on('change', '#userList input[type=checkbox]',  function() {
		var user_id = $(this).data("user_id");

		array_delete(selection, user_id);

		if ($(this).is(":checked")) {
			selection.push(user_id);
		}

		checkSelection();
	});

	$("#selectAll").click(function () {
		selection = allUsers;
		$("#userList input[type=checkbox]").prop('checked', true);
		checkSelection();
		return false;
	});

	$("#selectNone").click(function () {
		selection = [];
		$("#userList input[type=checkbox]").prop('checked', false);
		checkSelection();
		return false;
	});

	$("#selectInvert").click(function () {
		var newSelection = [];
		for(var i in allUsers)
		{
			if (selection.indexOf(allUsers[i]) == -1) {
				newSelection.push(allUsers[i]);
			}
		}
		selection = newSelection;

		$("#userList input[type=checkbox]").each(function() {
			var user_id = $(this).data("user_id");
			$(this).prop('checked', (selection.indexOf(user_id) != -1));
		});

		checkSelection();
		return false;
	});

	/**
	 * Action management
	 */
	$("[id^=action_]").hide();

	$("select[name=selectAction]").change(function () {
		$("#applyActionBlock .infos").hide();

		$("[id^=action_]").hide();

		$("#action_"+$(this).prop("value")).show();

		if ($(this).val() != -1) {
			$("#applyActionBlock").show();
		}
		else {
			$("#applyActionBlock").hide();
		}
	});

	$("#permitAction input, #permitAction select").click(function() {
		$("#applyActionBlock .infos").hide();
	});

	$("#applyAction").click(function() {
		var action = $("select[name=selectAction]").prop("value");
		var method = 'pwg.users.setInfo';
		var data = {
			pwg_token: pwg_token,
			user_id: selection
		};

		switch (action) {
		case 'delete':
			if (!$("input[name=confirm_deletion]").is(':checked')) {
				alert(missingConfirm);
				return false;
			}
			method = 'pwg.users.delete';
			break;
		case 'group_associate':
			method = 'pwg.groups.addUser';
			data.group_id = $("select[name=associate]").prop("value");
			break;
		case 'group_dissociate':
			method = 'pwg.groups.deleteUser';
			data.group_id = $("select[name=dissociate]").prop("value");
			break;
		case 'status':
			data.status = $("select[name=status]").prop("value");
			break;
		case 'enabled_high':
			data.enabled_high = $("input[name=enabled_high]:checked").val();
			break;
		case 'level':
			data.level = $("select[name=level]").val();
			break;
		case 'nb_image_page':
			data.nb_image_page = $("input[name=nb_image_page]").val();
			break;
		case 'theme':
			data.theme = $("select[name=theme]").val();
			break;
		case 'language':
			data.language = $("select[name=language]").val();
			break;
		case 'recent_period':
			data.recent_period = $("input[name=recent_period]").val();
			break;
		case 'expand':
			data.expand = $("input[name=expand]:checked").val();
			break;
		case 'show_nb_comments':
			data.show_nb_comments = $("input[name=show_nb_comments]:checked").val();
			break;
		case 'show_nb_hits':
			data.show_nb_hits = $("input[name=show_nb_hits]:checked").val();
			break;
		default:
			alert("Unexpected action");
			return false;
		}

		$.ajax({
			url: "../ws.php?format=json&method="+method,
			type:"POST",
			data: data,
			beforeSend: function() {
				$("#applyActionLoading").show();
			},
			success:function(data) {
				oTable.fnDraw();
				$("#applyActionLoading").hide();
				$("#applyActionBlock .infos").show();

				if (action == 'delete') {
					var allUsers_new = [];
					for(var i in allUsers)
					{
						if (selection.indexOf(allUsers[i]) == -1) {
							allUsers_new.push(allUsers[i]);
						}
					}
					allUsers = allUsers_new;
					selection = [];
					checkSelection();
				}
			},
			error:function(XMLHttpRequest, textStatus, errorThrows) {
				$("#applyActionLoading").hide();
			}
		});

		return false;
	});

});
