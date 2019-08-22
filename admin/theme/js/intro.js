$(function () {
	$('#check-upgrade').click(function (e) {
		$.ajax({
			type: 'GET',
			url: '../ws',
			dataType: 'json',
			data: { method: 'pwg.extensions.checkUpdates' },
			timeout: 5000,
			success: function (data) {
				if (data['stat'] != 'ok') {
					return;
				}

				phyxo_update = data.result.phyxo_need_update;
				ext_update = data.result.ext_need_update;
				if (!$(".warnings").is('div')) {
					if (phyxo_update || ext_update) {
						$("#content").prepend('<div class="warnings"><i class="eiw-icon fa-exclamation"></i><ul></ul></div>');
						if (phyxo_update) {
							$(".warnings ul").append('<li>' + phyxo_need_update_msg + '</li>');
						}
						if (ext_update) {
							$(".warnings ul").append('<li>' + ext_need_update_msg + '</li>');
						}
					} else {
						$("#content").prepend('<div class="warnings"><i class="eiw-icon fa-exclamation"></i><ul></ul></div>');
						$(".warnings ul").append('<li>' + phyxo_is_uptodate_msg + '</li>');
					}
				}
			}
		});
		e.preventDefault();
	});
});
