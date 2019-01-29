import Slider from 'bootstrap-slider';
import 'selectize';
import _ from 'underscore';

import DataTable from 'datatables.net';
import 'datatables.net-bs4';
import 'datatables.net-dt';
import 'datatables.net-select';
import 'datatables.net-buttons';
import 'sprintf-js';

$(function() {
    const users_list = $('#users-list');
    let datatable;

    if ($('#addUserForm').length>0) {
	$('.alert').hide();
	$('#addUserForm').submit(function() {
            $.ajax({
		url: '../ws.php?method=pwg.users.add',
		type: 'POST',
		data: $(this).serialize() + '&pwg_token=' + pwg_token,
		beforeSend: function() {
		    $('.alert').find('p').remove();
                    $('.alert').removeClass('alert-info').removeClass('alert-danger').hide();

                    if ($('input[name="username"]').val() == '') {
			$('.alert').addClass('alert-warning').addClass('show').append('<p>&#x2718; ' + phyxo_msg.missing_username+'</p>').show();

			return false;
                    }
		},
		success: function(json) {
                    const data = $.parseJSON(json);
                    if (data.stat == 'ok') {
			$('#addUserForm input[type="text"], #addUserForm input[type="password"]').val('');

			const new_user = data.result.users[0];
			$('.alert').addClass('alert-info').addClass('show').append('<p>&#x2714; ' + sprintf(phyxo_msg.new_user_pattern, new_user.username) + '</p>').show();
			datatable.ajax.reload();
			$('#addUserForm').hide();
                    } else {
			$('.alert').addClass('alert-danger').addClass('show').append('<p>&#x2718; ' + data.message + '</p>').show();
                    }
		}
            });

            return false;
	});
	$('#permitAction').hide();
    }

    if (users_list.length > 0) {
	datatable = users_list.DataTable({
	    lengthMenu: [5,10,25],
	    pageLength: users_list_config.pageLength,
	    ajax: {
		url: '../ws.php?method=pwg.users.getList&display=all',
		dataSrc: function(json) {
		    return json.result.users;
		},
	    },
	    columns: users_list_config.columns,
	    language: users_list_config.language,
	    select: {
		style: 'multi',
		selector: 'td:first-child input[type="checkbox"]'
	    },
	    dom: 'lifrtpB',
	    buttons: [
		{
                    text: users_list_config.language.select.select_all,
		    className: 'btn btn-primary',
                    action: function () {
			datatable.rows().select();
                    }
		},
		{
                    text: users_list_config.language.select.select_none,
		    className: 'btn btn-secondary',
                    action: function () {
			datatable.rows().deselect();
                    }
		},
		{
                    text: users_list_config.language.select.invert_selection,
		    className: 'btn btn-success',
                    action: function () {
			const rows_selected = datatable.rows({selected:true});
			datatable.rows().select();
			rows_selected.deselect();
                    }
		}
	    ],
	    'columnDefs': [
		{
		    targets: 0,
		    searchable: false,
		    orderable: false,
		    className: 'select-checkbox',
		    render: function (data, type, user, meta) {
			return '<input type="checkbox" data-user_id="'+data+'">';
		    }
		},
		{
		    targets: 1,
		    render: function(data, type, user, meta) {
			return '<span class="details-control"><i class="fa fa-edit"></i> '+ data + '</span>';
		    }
		},
		{
		    targets: 4,
		    render: function(data, type, user, meta) {
			if (data) {
			    return data.map(function(group) {
				return groups[group];
			    }).join(', ');
			} else {
			    return '';
			}
		    }
		},
		{
		    targets: 5,
		    render: function(data, type, user, meta) {
			const level_keys = Object.keys(levels);

			if (data && level_keys.includes(data)) {
			    return levels[data];
			} else {
			    return '';
			}
		    }
		}
	    ],
	});

	datatable.on('select', function (e, dt, type, indexes) {
	    datatable.rows(indexes).every(function(id, tableCounter, rowCounter) {
		$(this.node()).find('[type="checkbox"]').prop('checked', true);
	    });

	    if (datatable.rows({selected: true}).count()>0) {
		$('#forbidAction').hide();
		$('#permitAction').show();
	    }
	});

	datatable.on('deselect', function (e, dt, type, indexes) {
	    datatable.rows(indexes).every(function(id, tableCounter, rowCounter) {
		$(this.node()).find('[type="checkbox"]').prop('checked', false);
	    });

	    if (datatable.rows({selected: true}).count() === 0) {
		$('#forbidAction').show();
		$('#permitAction').hide();
	    }
	});

	datatable.on('click', '.details-control', function() {
	    const tr = $(this).parents('tr');
            const row = datatable.row(tr);

            if (row.child.isShown()) {
		row.child.hide();
		tr.removeClass('shown');
            } else {
                _.templateSettings.variable = 'user';
                const template = _.template($('script.userDetails').html());

		let formUser = prepareFormUser(row.data());
		row.child(template(formUser)).show();
		tr.addClass('shown');

		$('[data-selectize=groups]').selectize({
		    valueField: 'value',
		    labelField: 'label',
		    searchField: ['label'],
		    plugins: ['remove_button']
		});

		const groupSelectize = $('[data-selectize=groups]')[0].selectize;

		groupSelectize.load(function(callback) {
		    callback(formUser.groupOptions);
		});

		$.each(
		    $.grep(formUser.groupOptions, function(group) {
			return group.isSelected;
		    }),
		    function(i, group) {
			groupSelectize.addItem(group.value);
		    }
		);

		/* nb_image_page slider */
                const nb_image_page_init = nb_image_page_values.indexOf(Number($('#user' + formUser.id + ' input[name="nb_image_page"]').val()));
                $('#user' + formUser.id + ' .nb_image_page_infos').html(getNbImagePageInfoFromIdx(nb_image_page_init));

		const slider_images = new Slider('#user' + formUser.id + ' [name="nb_image_page"]', {
		    min: 0,
		    max: nb_image_page_values.length -1,
		    value: nb_image_page_init,
		});
		slider_images.on('change', function(values) {
		    $('#user' + formUser.id + ' .nb_image_page_infos').html(getNbImagePageInfoFromIdx(values.newValue));
		});

                /* recent_period slider */
                const recent_period_init = $('#user' + formUser.id + ' input[name="recent_period"]').val();
                $('#user' + formUser.id + ' .recent_period_infos').html(getRecentPeriodInfoFromIdx(recent_period_init));

		const slider_period = new Slider('#user' + formUser.id + ' [name="recent_period"]', {
		    min: 0,
		    max: recent_period_values.length -1,
		    value: recent_period_init,
		});
		slider_period.on('change', function(values) {
		    $('#user' + formUser.id + ' .recent_period_infos').html(getRecentPeriodInfoFromIdx(values.newValue));
		});

		$('#user' + formUser.id + ' input[type=submit]').prop('disabled', true);
            }
	});

	function prepareFormUser(user) {
	    /* Prepare data for template */
	    user.statusOptions = [];
	    $('#action select[name="status"] option').each(function() {
		let option = { value: $(this).val(), label: $(this).html(), isSelected: false };

		if (user.status == $(this).val()) {
		    option.isSelected = true;
		}

		user.statusOptions.push(option);
	    });

	    user.levelOptions = [];
	    $('#action select[name="level"] option').each(function() {
		let option = { value: $(this).val(), label: $(this).html(), isSelected: false };

		if (user.level == $(this).val()) {
		    option.isSelected = true;
		}

		user.levelOptions.push(option);
	    });

	    user.groupOptions = [];
	    $('#action select[name=associate] option').each(function() {
		let option = { value: $(this).val(), label: $(this).html(), isSelected: false };

		if (user.groups && user.groups.includes(parseInt($(this).val(), 10))) {
		    option.isSelected = true;
		}

		user.groupOptions.push(option);
	    });

	    user.themeOptions = [];
	    $('#action select[name=theme] option').each(function() {
		let option = { value: $(this).val(), label: $(this).html(), isSelected: false };

		if (user.theme == $(this).val()) {
		    option.isSelected = true;
		}

		user.themeOptions.push(option);
	    });

	    user.languageOptions = [];
	    $('#action select[name=language] option').each(function() {
		let option = { value: $(this).val(), label: $(this).html(), isSelected: false };

		if (user.language == $(this).val()) {
		    option.isSelected = true;
		}

		user.languageOptions.push(option);
	    });

	    user.isGuest = user.id == guestUser;
	    user.isProtected = protectedUsers.indexOf(user.id) != -1;

	    user.registeredOn_string = sprintf(
		phyxo_msg.registeredOn_pattern,
		user.registration_date_string,
		user.registration_date_since
	    );

	    user.lastVisit_string = '';
	    if (user.last_visit !== undefined) {
		user.lastVisit_string = sprintf(phyxo_msg.lastVisit_pattern, user.last_visit_string, user.last_visit_since);
	    }

	    user.email = user.email || '';

	    $('#action select[name=status] option').each(function() {
		if (user.status == $(this).val()) {
		    user.statusLabel = $(this).html();
		}
	    });

	    return user;
	}

	const recent_period_values = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 25, 30, 40, 50, 60, 80, 99];

	function getRecentPeriodInfoFromIdx(idx) {
	    return phyxo_msg.days.replace('%d', recent_period_values[idx]);
	}

	const nb_image_page_values = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 35, 40, 45, 50, 60, 70, 80, 90, 100, 200, 300, 500, 999];

	function getNbImagePageInfoFromIdx(idx) {
	    return phyxo_msg.photos_per_page.replace('%d', nb_image_page_values[idx]);
	}

	$(document).on('click', '#changePassword [type="submit"]', function() {
	    const userId = $(this)
		.parentsUntil('form')
		.parent()
		.find('input[name=user_id]')
		.val();

	    $.ajax({
		url: '../ws.php?method=pwg.users.setInfo',
		type: 'POST',
		data: {
		    pwg_token: pwg_token,
		    user_id: userId,
		    password: $('#changePassword input[type="text"]').val()
		},
		beforeSend: function() {
		    $('#changePassword input[type="text"]').val('');
		    $('#changePassword').toggle('slow');
		    $('.alert').find('p').remove();
		    $('.alert').removeClass('alert-info').removeClass('alert-danger').hide();
		},
		success: function(data) {
		    $('.alert').addClass('alert-info').addClass('show').append('<p>&#x2714; ' + phyxo_msg.user_password_updated + '</p>').show();
		    datatable.ajax.reload();
		},
		error: function(XMLHttpRequest, textStatus, errorThrows) {}
	    });

	    return false;
	});

	$(document).on('click', '#changeUsername [type="submit"]', function() {
	    const userId = $(this)
		.parentsUntil('form')
		.parent()
		.find('input[name=user_id]')
		.val();

	    $.ajax({
		url: '../ws.php?method=pwg.users.setInfo',
		type: 'POST',
		data: {
		    pwg_token: pwg_token,
		    user_id: userId,
		    username: $('#changeUsername input[type=text]').val()
		},
		beforeSend: function() {
		    $('#changeUsername input[type="text"]').val('');
		    $('#changeUsername').toggle('slow');
		    $('.alert').find('p').remove();
		    $('.alert').removeClass('alert-info').removeClass('alert-danger').hide();
		},
		success: function(json) {
		    const data = $.parseJSON(json);
		    $('.alert')
			.addClass('alert-info')
			.addClass('show')
			.append('<p>&#x2714; '+sprintf(phyxo_msg.username_changed_pattern, '<strong>'+data.result.users[0].username+'</strong>')+'</p>').show();
		    datatable.ajax.reload();
		},
		error: function(XMLHttpRequest, textStatus, errorThrows) {}
	    });

	    return false;
	});

	$(document).on('change', '.userProperty input, .userProperty select', function() {
	    const userId = $(this)
		.parentsUntil('form')
		.parent()
		.find('input[name=user_id]')
		.val();

	    $('#user' + userId + ' input[type=submit]').prop('disabled', false);
	});

	/* delete user */
	$(document).on('click', '#user-delete', function() {
	    if (!confirm(phyxo_msg.are_you_sure)) {
		return false;
	    }

	    const userId = $(this).data('user_id');
	    const username = $(this).data('username');

	    $.ajax({
		url: '../ws.php?method=pwg.users.delete',
		type: 'POST',
		data: {
		    user_id: userId,
		    pwg_token: pwg_token
		},
		beforeSend: function() {
		    $('.alert').find('p').remove();
		    $('.alert').removeClass('alert-info').removeClass('alert-danger').hide();
		},
		success: function(data) {
		    datatable.ajax.reload();
		    $('.alert').addClass('alert-info').addClass('show').append('<p>&#x2714; '+sprintf(phyxo_msg.user_deleted, username)+'</p>').show();
		},
		error: function(XMLHttpRequest, textStatus, errorThrows) {
		}
	    });

	    return false;
	});

	$(document).on('click', '.user-infos input[type=submit]', function() {
	    const userId = $(this).data('user_id');

	    let fd = new FormData();
	    fd.append('pwg_token', pwg_token);
	    fd.append('user_id', userId);

	    if ($('#user' + userId + ' input[name="email"]').val()) {
		fd.append('email', $('#user' + userId + ' input[name="email"]').val());
	    }

	    if ($('#user' + userId + ' [name="status"]').val()) {
		fd.append('status', $('#user' + userId + ' [name="status"]').val());
	    }

	    if ($('#user' + userId + ' input[name="level"]').val()) {
		fd.append('level', $('#user' + userId + ' input[name="level"]').val());
	    }

	    fd.append('enabled_high', $('#user' + userId + ' input[name="enabled_high"]').is(':checked'));

	    if ($('#user' + userId + ' select[name="group_id[]"]').val().length>0) {
		$('#user' + userId + ' select[name="group_id[]"]').val().forEach(function(group) {
		    fd.append('group_id[]', group);
		});
	    } else {
		fd.append('group_id', -1);
	    }

	    fd.append('nb_image_page', nb_image_page_values[$('#user' + userId + ' input[name="nb_image_page"]').val()]);

	    if ($('#user' + userId + ' [name="theme"]').val()) {
		fd.append('theme', $('#user' + userId + ' [name="theme"]').val());
	    }

	    if ($('#user' + userId + ' [name="language"]').val()) {
		fd.append('language', $('#user' + userId + ' [name="language"]').val());
	    }

	    fd.append('recent_period', $('#user' + userId + ' input[name="recent_period"]').val());
	    fd.append('expand', $('#user' + userId + ' input[name="expand"]').is(':checked'));
	    fd.append('show_nb_comments', $('#user' + userId + ' input[name="show_nb_comments"]').is(':checked'));
	    fd.append('show_nb_hits', $('#user' + userId + ' input[name="show_nb_hits"]').is(':checked'));

	    $.ajax({
		url: '../ws.php?method=pwg.users.setInfo',
		type: 'POST',
		data: fd,
 		processData: false,
 		contentType: false,
		beforeSend: function() {
		    $('.alert').find('p').remove();
		    $('.alert').removeClass('alert-info').removeClass('alert-danger').hide();
		},
		success: function(data) {
		    datatable.ajax.reload();
		    $('.alert').addClass('alert-info').addClass('show').append('<p>&#x2714; '+phyxo_msg.user_infos_updated+'</p>').show();
		},
		error: function(XMLHttpRequest, textStatus, errorThrows) {
		}
	    });

	    return false;
	});

	/**
	 * Action management
	 */
	$('[id^=action_]').hide();
	$('#permitAction input[type=submit]').prop('disabled', true);
	$('select[name="selectAction"]').change(function() {
	    $('#permitAction input[type=submit]').prop('disabled', false);
	    $('[id^=action_]').hide();
	    $('#action_' + $(this).prop('value')).show();

	    /* nb_image_page slider */
	    const nb_image_page_init = $('#action_nb_image_page input[name="nb_image_page"]').val();
            $('#action_nb_image_page .nb_image_page_infos').html(getNbImagePageInfoFromIdx(nb_image_page_init));

	    const slider_images = new Slider('#action_nb_image_page [name="nb_image_page"]', {
		min: 0,
		max: nb_image_page_values.length - 1,
		value: nb_image_page_init,
	    });
	    slider_images.on('change', function(values) {
		$('#action_nb_image_page .nb_image_page_infos').html(getNbImagePageInfoFromIdx(values.newValue));
	    });

            /* recent_period slider */
            const recent_period_init = $('#action_recent_period input[name="recent_period"]').val();
            $('#action_recent_period .recent_period_infos').html(getRecentPeriodInfoFromIdx(recent_period_init));

	    const slider_period = new Slider('#action_recent_period [name="recent_period"]', {
		min: 0,
		max: recent_period_values.length - 1,
		value: recent_period_init,
	    });
	    slider_period.on('change', function(values) {
		$('#action_recent_period .recent_period_infos').html(getRecentPeriodInfoFromIdx(values.newValue));
	    });

            if ($(this).val() != -1) {
		$('#applyActionBlock').show();
            } else {
		$('#applyActionBlock').hide();
            }
	});

	$('#applyAction').click(function() {
            const action = $('select[name="selectAction"]').prop('value');
            let method = 'pwg.users.setInfo';
	    const rowData = datatable.rows({selected: true}).data();

            const data = {
		pwg_token: pwg_token,
		user_id: rowData.toArray().map(data => data.id)
            };

            switch (action) {
		case 'delete':
                    if (!$('input[name="confirm_deletion"]').is(':checked')) {
			alert(phyxo_msg.missing_confirm);
			return false;
                    }
                    method = 'pwg.users.delete';
                    break;
		case 'group_associate':
                    method = 'pwg.groups.addUser';
                    data.group_id = $('select[name="associate"]').prop('value');
                    break;
		case 'group_dissociate':
                    method = 'pwg.groups.deleteUser';
                    data.group_id = $('select[name="dissociate"]').prop('value');
                    break;
		case 'status':
                    data.status = $('select[name="status"]').prop('value');
                    break;
		case 'enabled_high':
                    data.enabled_high = $('input[name="enabled_high"]:checked').val();
                    break;
		case 'level':
                    data.level = $('select[name="level"]').val();
                    break;
		case 'nb_image_page':
                    data.nb_image_page = $('input[name="nb_image_page"]').val();
                    break;
		case 'theme':
                    data.theme = $('select[name="theme"]').val();
                    break;
		case 'language':
                    data.language = $('select[name="language"]').val();
                    break;
		case 'recent_period':
                    data.recent_period = $('input[name="recent_period"]').val();
                    break;
		case 'expand':
                    data.expand = $('input[name="expand"]:checked').val();
                    break;
		case 'show_nb_comments':
                    data.show_nb_comments = $('input[name="show_nb_comments"]:checked').val();
                    break;
		case 'show_nb_hits':
                    data.show_nb_hits = $('input[name="show_nb_hits"]:checked').val();
                    break;
		default:
                    alert('Unexpected action');
                    return false;
            }

            $.ajax({
		url: '../ws.php?method=' + method,
		type: 'POST',
		data: data,
		beforeSend: function() {
		    $('.alert').find('p').remove();
                    $('.alert').removeClass('alert-info').removeClass('alert-danger').hide();
		},
		success: function(data) {
                    datatable.ajax.reload();
		    $('[name="selectAction"]').val(-1);
		    $('[id^=action_]').hide();
		    $('#permitAction input[type=submit]').prop('disabled', true);
		    $('#forbidAction').show();
		    $('#permitAction').hide();
		    $('.alert').addClass('alert-info').addClass('show').append('<p>&#x2714; '+phyxo_msg.users_updated+'</p>').show();
		},
		error: function(XMLHttpRequest, textStatus, errorThrows) {
		}
            });

            return false;
	});
    }
});
