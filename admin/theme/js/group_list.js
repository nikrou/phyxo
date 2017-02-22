$(function() {
	/**
	 * Add group
	 */
	$("#addGroup").click(function() {
		$("#addGroupForm").toggle();
		$("input[name=groupname]").focus();
		return false;
	});

	$("#addGroupClose").click(function() {
		$("#addGroupForm").hide();
		return false;
	});

	$('.groups input').change(function () { $(this).parent('p').toggleClass('group_select'); });
	$(".grp_action").hide();
	$("input.group_selection").click(function() {

		var nbSelected = 0;
		nbSelected = $("input.group_selection").filter(':checked').length;

		if (nbSelected == 0) {
			$("#permitAction").hide();
			$("#forbidAction").show();
		}
		else {
			$("#permitAction").show();
			$("#forbidAction").hide();
		}
		$("p[group_id="+$(this).prop("value")+"]").each(function () {
			$(this).toggle();
		});

		if (nbSelected<2) {
			$("#two_to_select").show();
			$("#two_atleast").hide();
		}
		else {
			$("#two_to_select").hide();
			$("#two_atleast").show();
		}
	});
	$("[id^=action_]").hide();
	$("select[name=selectAction]").change(function () {
		$("[id^=action_]").hide();
		$("#action_"+$(this).prop("value")).show();
		if ($(this).val() != -1 ) {
			$("#applyActionBlock").show();
		}
		else {
			$("#applyActionBlock").hide();
		}
	});
});


