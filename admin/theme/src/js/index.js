import '../scss/style.scss';

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
});
