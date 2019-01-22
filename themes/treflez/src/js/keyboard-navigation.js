document.onkeydown = function(e) {
    const keyToRel = [];
    keyToRel[35] = { rel: 'last', ctrlKey: true };
    keyToRel[36] = { rel: 'first', ctrlKey: true };
    keyToRel[37] = { rel: 'prev', ctrlKey: false };
    keyToRel[38] = { rel: 'up', ctrlKey: true };
    keyToRel[39] = { rel: 'next', ctrlKey: false };

    e = e || window.event;
    if (e.altKey) return true;
    var target = e.target || e.srcElement;
    if (target && target.type) return true;
    var keyCode = e.keyCode || e.which;

    if (keyCode && keyToRel[keyCode]) {
	if (keyToRel[keyCode]['ctrlKey'] && !e.ctrlKey) {
	    return;
	}
	const link = $('link[rel="'+keyToRel[keyCode]['rel']+'"]');
	if (link.length) {
	    // @TODO: prevent scroll when end key is pressed
            document.location = link.attr('href');
	}
    }
}
