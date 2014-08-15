$(function(){
	$(".menuPos").hide();
	$(".drag_button").show();
	$(".menuLi").css("cursor","move");
	$(".menuUl").sortable({
		axis: "y",
		opacity: 0.8
	});
	$("input[name^='hide_']").click(function() {
		men = this.name.split('hide_');
		if (this.checked) {
			$("#menu_"+men[1]).addClass('menuLi_hidden');
		} else {
			$("#menu_"+men[1]).removeClass('menuLi_hidden');
		}
	});
	$("#menuOrdering").submit(function(){
		ar = $('.menuUl').sortable('toArray');
		for(i=0;i<ar.length;i++) {
			men = ar[i].split('menu_');
			document.getElementsByName('pos_' + men[1])[0].value = i+1;
		}
	});
});

