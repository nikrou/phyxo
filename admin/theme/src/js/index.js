import '../scss/style.scss';

import $ from "jquery";
import "bootstrap";

$(function() {
    if (menuitem_active!==undefined) {
	$('aside[role="navigation"] a[href="' + menuitem_active + '"]')
	    .addClass('show')
	    .parentsUntil('.accordion').addClass('show');
    }

    $('a.externalLink').click(function() {
	window.open(this.attr("href"));

	return false;
    });

    $('[data-confirm]').click(function() {
	const button = $(this);
	const fetch_url = button.data('action');
	const http_method = button.data('method')!==undefined ? button.data('method') : 'GET';
	let fetch_params = {};
	if (http_method==='POST') {
	    fetch_params.headers = { "Content-Type": "application/x-www-form-urlencoded" }; // @TODO: send application/json but need to retrieve stream in WS
	    const post_params = [];
	    for (let param in button.data('data')) {
		post_params.push(param + '=' + encodeURIComponent(button.data('data')[param]));
	    }
	    fetch_params.body = post_params.join('&');
	} else {
	    fetch_params = button.data('data');
	}
	fetch_params.method = http_method;
	fetch_params.credentials = 'same-origin';

	$('#confirm-delete').on('click', '.btn.btn-delete', function(e) {
	    fetch(
		fetch_url, fetch_params
	    ).then(function(response) {
		if (response.ok) {
		    $(button.data('delete')).remove();
		} else {
		    console.log(response.statusText);
		}
	    }).catch(function(err) {
		console.log(err);
	    });
	});
    });

    $.fn.fontCheckbox = function() {
	this.find('input[type=checkbox], input[type=radio]').each(function() {
	    if (!$(this).is(':checked')) {
		$(this).prev().toggleClass('fa-check-square fa-square-o');
	    }
	});
	this.find('input[type=checkbox]').on('change', function() {
	    $(this).prev().toggleClass('fa-check-square fa-square-o');
	});
	this.find('input[type=radio]').on('change', function() {
	    $(this).closest('.font-checkbox').find('input[type=radio][name='+ $(this).attr('name') +']')
		   .prev().toggleClass('fa-check-square fa-square-o');
	});
    };

    // init fontChecbox everywhere
    $('.font-checkbox').fontCheckbox();
});
