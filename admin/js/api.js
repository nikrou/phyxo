$(function() {
    // global vars
    var cachedMethods = [];
    var ws_url = "http://";

    // automatic detection of ws_url
    match = document.location.toString().match(/^(https?.*\/)admin\/api.php?/);
    if (match==null) {
	askForUrl();
    } else {
	ws_url = match[1]+'ws.php';
	getMethodList();
    }

    // manual set of ws_url
    $("#urlForm").submit(function() {
	ws_url = $(this).children("input[name='ws_url']").val();
	getMethodList();
	return false;
    });

    // invoke buttons
    $("#invokeMethod").click(function() {
	invokeMethod($("#methodName").html(), false);
	return false;
    });
    $("#invokeMethodBlank").click(function() {
	invokeMethod($("#methodName").html(), true);
	return false;
    });

    // resizable iframe
    $("#increaseIframe").click(function() {
	$("#iframeWrapper").css('height', $("#iframeWrapper").height()+100);
	adaptHeight();
    });
    $("#decreaseIframe").click(function() {
	if ($("#iframeWrapper").height() > 200) {
	    $("#iframeWrapper").css('height', $("#iframeWrapper").height()-100);
	    adaptHeight();
	}
    });

    // mask all wrappers
    function resetDisplay() {
	$("#errorWrapper").hide();
	$("#methodWrapper").hide();
	$("#methodName").hide();
	$("#urlForm").hide();
	$("#methodDescription blockquote").empty();
	$("#methodDescription").hide();
	$("#requestDisplay").hide();
	$("#invokeFrame").attr('src','');
    }

    // give the same size to methods list and main page
    function adaptHeight() {
	$("#the_page").css('height', 'auto');
	$("#the_methods").css('height', 'auto');

	min_h = $(window).height()-$("#the_header").outerHeight()-$("#the_footer").outerHeight()-3;
	h = Math.max(min_h, Math.max($("#the_methods").height(), $("#the_page").height()));

	$("#the_page").css('height', h);
	$("#the_methods").css('height', h);
    }

    // display error wrapper
    function displayError(error) {
	resetDisplay();
	$("#errorWrapper").html("<b>Error:</b> "+ error).show();
	adaptHeight();
    }

    // display ws_url form
    function askForUrl() {
	displayError("can't contact web-services, please give absolute url to 'ws.php'");
	if ($("#urlForm input[name='ws_url']").val() == "") {
	    $("#urlForm input[name='ws_url']").val(ws_url);
	}
	$("#urlForm").show();
    }

    // parse Piwigo JSON
    function parsePwgJSON(json) {
	try {
	    resp = jQuery.parseJSON(json);
	    if (resp==null | resp.result==null | resp.stat==null | resp.stat!='ok') {
		throw new Error();
	    }
	}
	catch(e) {
	    displayError("unable to parse JSON string");
	    resp = {"stat": "ko", "result": "null"};
	}

	return resp.result;
    }

    // fetch methods list
    function getMethodList() {
	resetDisplay();

	$.ajax({
	    type: "GET",
	    url: ws_url,
	    data: { format: "json", method: "reflection.getMethodList" }
	}).done(function(result) {
	    result = parsePwgJSON(result);

	    if (result!=null) {
		methods = result.methods;

		var ml = '';
		for (var i=0; i<methods.length; i++)
		{
		    ml += '<li><a href="#top">'+ methods[i]+'</a></li>';
		}
		$("#methodsList").html(ml).show();

		adaptHeight();

		// trigger method selection
		$("#methodsList li a").click(function() {
		    selectMethod($(this).html());
		});
	    }
	}).error(function(jqXHR, textStatus, errorThrown) {
	    askForUrl();
	});
    }

    // select method
    function selectMethod(methodName) {
	$("#introMessage").hide();
	if (cachedMethods.methodName) {
	    fillNewMethod(methodName);
	} else {
	    $.ajax({
		type: "GET",
		url: ws_url,
		data: { format: "json", method: "reflection.getMethodDetails", methodName: methodName }
	    }).done(function(result) {
		result = parsePwgJSON(result);

		if (result!=null) {
		    if (result.options.post_only || result.options.admin_only) {
			var onlys = '<div class="onlys">';
			if (result.options.post_only) {
			    onlys+= 'POST only. ';
			}
			if (result.options.admin_only) {
			    onlys+= 'Admin only. ';
			}
			onlys+= '</div>';

			result.description = onlys + result.description;
		    }
		    cachedMethods[ methodName ] = result;
		    fillNewMethod(methodName);
		}
	    }).error(function(jqXHR, textStatus, errorThrown) {
		displayError("unknown error");
	    });
	}
    }

    // display method details
    function fillNewMethod(methodName) {
	resetDisplay();

	method = cachedMethods[ methodName ];

	$("#methodName").html(method.name).show();

	if (method.description != "") {
	    $("#methodDescription blockquote").html(method.description);
	    $("#methodDescription").show();
	}

	$("#requestFormat").val(method.options.post_only ? 'post' : 'get');

	var methodParams = '';
	if (method.params && method.params.length>0) {
	    for (var i=0; i<method.params.length; i++) {
		var param = method.params[i],
		    isOptional = param.optional,
		    acceptArray = param.acceptArray,
		    defaultValue = param.defaultValue == null ? '' : param.defaultValue,
		    info = param.info == null ? '' : '<a class="methodInfo" title="'+ param.info.replace(/"/g, '&quot;') + '">i</a>',
		    type = '';

		if (param.type.match(/bool/)) type+= '<span class=type>B</span>';
		if (param.type.match(/int/)) type+= '<span class=type>I</span>';
		if (param.type.match(/float/)) type+= '<span class=type>F</span>';
		if (param.type.match(/positive/)) type+= '<span class=subtype>+</span>';
		if (param.type.match(/notnull/)) type+= '<span class=subtype>&oslash;</span>';

		// if an array is direclty printed, the delimiter is a comma where we use a pipe
		if (typeof defaultValue == 'object') {
		    defaultValue = defaultValue.join('|');
		}

		methodParams+= '<tr>'+
		    '<td>'+ param.name + info +'</td>'+
		    '<td class="mini">'+ (isOptional ? '?':'*') + (acceptArray ? ' []':'') +'</td>'+
		    '<td class="mini">'+ type +'</td>'+
		    '<td class="input"><input type="text" class="methodParameterValue" data-id="'+ i +'" value="'+ defaultValue +'"></td>'+
		    '<td class="mini"><input type="checkbox" class="methodParameterSend" data-id="'+ i +'" '+ (isOptional ? '':'checked="checked"') +'></td>'+
		    '</tr>';
	    }
	} else {
	    methodParams = '<tr><td colspan="4">This method takes no parameters</td></tr>';
	}

	$("#methodParams tbody").html(methodParams);
	$("#methodWrapper").show();

	adaptHeight();

	// trigger field modification
	$("input.methodParameterValue").change(function() {
	    $("input.methodParameterSend[data-id='"+ $(this).data('id') +"']").attr('checked', 'checked');
	});
    }

    // invoke method
    function invokeMethod(methodName, newWindow) {
	var method = cachedMethods[ methodName ];

	var reqUrl = ws_url +"?format="+ $("#responseFormat").val();

	// GET
	if ($("#requestFormat").val() == 'get') {
	    reqUrl+= "&method="+ methodName;

	    for (var i=0; i<method.params.length; i++) {
		if (! $("input.methodParameterSend[data-id='"+ i +"']").is(":checked")) {
		    continue;
		}

		var paramValue = $("input.methodParameterValue[data-id='"+ i +"']").val();

		var paramSplitted = paramValue.split('|');
		if (method.params[i].acceptArray &&  paramSplitted.length > 1) {
		    $.each(paramSplitted, function(v) {
			reqUrl+= '&'+ method.params[i].name +'[]='+ paramSplitted[v];
		    });
		} else {
		    reqUrl+= '&'+ method.params[i].name +'='+ paramValue;
		}
	    }

	    if (newWindow) {
		window.open(reqUrl);
	    } else {
		$("#invokeFrame").attr('src', reqUrl);
	    }

	    $('#requestDisplay').show()
		.find('.url').html(reqUrl).end()
		.find('.params').hide();
	} else { // POST
	    var params = {};

	    var form = $("#invokeForm");
	    form.attr('action', reqUrl);

	    var t = '<input type="hidden" name="method" value="'+ methodName +'">';

	    for (var i=0; i<method.params.length; i++) {
		if (! $("input.methodParameterSend[data-id='"+ i +"']").is(":checked")) {
		    continue;
		}

		var paramValue = $("input.methodParameterValue[data-id='"+ i +"']").val(),
		    paramName = method.params[i].name,
		    paramSplitted = paramValue.split('|');

		if (method.params[i].acceptArray &&  paramSplitted.length > 1) {
		    params[paramName] = [];

		    $.each(paramSplitted, function(i, value) {
			params[paramName].push(value);
			t+= '<input type="hidden" name="'+ paramName +'[]" value="'+ value +'">';
		    });
		} else {
		    params[paramName] = paramValue;
		    t+= '<input type="hidden" name="'+ paramName +'" value="'+ paramValue +'">';
		}
	    }

	    form.html(t);
	    form.attr('target', newWindow ? "_blank" : "invokeFrame");
	    form.submit();

	    $('#requestDisplay').show()
		.find('.url').html(reqUrl).end()
		.find('.params').show().html(JSON.stringify(params, null, 4));
	}

	return false;
    }
});
