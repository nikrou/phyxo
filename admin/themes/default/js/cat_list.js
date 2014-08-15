$(function() {
	$(".drag_button").show();
	$(".categoryLi").css("cursor","move");
	$(".categoryUl").sortable({
		axis: "y",
		opacity: 0.8,
		update : function() {
			$("#manualOrder").show();
			$("#notManualOrder").hide();
			$("#formAutoOrder").hide();
			$("#formCreateAlbum").hide();
		}
	});

	$("#categoryOrdering").submit(function(){
		ar = $('.categoryUl').sortable('toArray');
		for(i=0;i<ar.length;i++) {
			cat = ar[i].split('cat_');
			document.getElementsByName('catOrd[' + cat[1] + ']')[0].value = i;
		}
	});

	$("input[name=order_type]").click(function () {
		$("#automatic_order_params").hide();
		if ($("input[name=order_type]:checked").val() == "automatic") {
			$("#automatic_order_params").show();
		}
	});

	$("#addAlbumOpen").click(function(){
		$("#formCreateAlbum").toggle();
		$("input[name=virtual_name]").focus();
		$("#formAutoOrder").hide();
	});

	$("#addAlbumClose").click(function(){
		$("#formCreateAlbum").hide();
	});


	$("#autoOrderOpen").click(function(){
		$("#formAutoOrder").toggle();
		$("#formCreateAlbum").hide();
	});

	$("#autoOrderClose").click(function(){
		$("#formAutoOrder").hide();
	});

	$("#cancelManualOrder").click(function(){
		$(".categoryUl").sortable("cancel");
		$("#manualOrder").hide();
		$("#notManualOrder").show();
	});
});
