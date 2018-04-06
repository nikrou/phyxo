$(function() {
    $(".albums").sortable({
	axis: "y",
	opacity: 0.8,
	update : function() {
	    $("#manualOrder").show();
	}
    });

    $("#categoryOrdering").submit(function(){
	ar = $('.albums').sortable('toArray');
	for(i=0;i<ar.length;i++) {
	    cat = ar[i].split('cat_');
	    document.getElementsByName('catOrd[' + cat[1] + ']')[0].value = i;
	}
    });
});
