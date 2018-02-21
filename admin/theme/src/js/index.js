import '../scss/style.scss';

import "bootstrap";

$(function() {
    console.log('jQuery loaded!');
    if (menuitem_active!==undefined) {
	console.log('menuitem_active', menuitem_active);

	$('aside[role="navigation"] a[href="' + menuitem_active + '"]')
	    .addClass('show')
	    .parentsUntil('.accordion').addClass('show');
    }

    $('a.externalLink').click(function() {
	window.open(this.attr("href"));

	return false;
    });
});
