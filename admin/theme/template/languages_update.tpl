{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Languages'|translate}</a></li>
    <li class="breadcrumb-item">{'Updates'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='jquery.ajaxmanager' load='footer' require='jquery' path='admin/theme/js/plugins/jquery.ajaxmanager.js'}
    {combine_script id='jquery.jgrowl' load='footer' require='jquery' path='admin/theme/js/plugins/jquery.jgrowl.js'}
    {combine_css path="admin/theme/js/plugins/jquery.jgrowl.css"}

    {footer_script require='jquery.ui.effect-blind,jquery.ajaxmanager,jquery.jgrowl'}
    var ws_url = '{$ws}';
    var pwg_token = '{$csrf_token}';
    var extType = '{$EXT_TYPE}';
    var confirmMsg  = '{'Are you sure?'|translate|@escape:'javascript'}';
    var errorHead   = '{'ERROR'|translate|@escape:'javascript'}';
    var successHead = '{'Update Complete'|translate|@escape:'javascript'}';
    var errorMsg    = '{'an error happened'|translate|@escape:'javascript'}';
    var restoreMsg  = '{'Reset ignored updates'|translate|@escape:'javascript'}';

    {literal}
    var todo = 0;
    var queuedManager = $.manageAjax.create('queued', {
    queue: true,
    maxRequests: 1,
    beforeSend: function() { autoupdate_bar_toggle(1); },
    complete: function() { autoupdate_bar_toggle(-1); }
    });

    function updateAll() {
    if (confirm(confirmMsg)) {
    jQuery('.updateExtension').each( function() {
    if (jQuery(this).parents('div').css('display') == 'block')
    jQuery(this).click();
    });
    }
    };

    function ignoreAll() {
    jQuery('.ignoreExtension').each( function() {
    if (jQuery(this).parents('div').css('display') == 'block')
    jQuery(this).click();
    });
    };

    function resetIgnored() {
    jQuery.ajax({
    type: 'GET',
    url: ws_url,
    dataType: 'json',
    data: { method: 'pwg.extensions.ignoreUpdate', reset: true, type: extType, pwg_token: pwg_token },
    success: function(data) {
    if (data['stat'] == 'ok') {
    jQuery(".pluginBox, fieldset").show();
    jQuery("#update_all").show();
    jQuery("#ignore_all").show();
    jQuery("#up_to_date").hide();
    jQuery("#reset_ignore").hide();
    jQuery("#ignored").hide();
    checkFieldsets();
    }
    }
    });
    };

    function checkFieldsets() {
    var types = new Array('plugins', 'themes', 'languages');
    var total = 0;
    var ignored = 0;
    for (i=0;i<3;i++) {
    nbExtensions = 0;
    jQuery("div[id^='"+types[i]+"_']").each(function(index) {
    if (jQuery(this).css('display') == 'block')
    nbExtensions++;
    else
    ignored++;
    });
    total = total + nbExtensions;
    if (nbExtensions == 0)
    jQuery("#"+types[i]).hide();
    }

    if (total == 0) {
    jQuery("#update_all").hide();
    jQuery("#ignore_all").hide();
    jQuery("#up_to_date").show();
    }
    if (ignored > 0) {
    jQuery("#reset_ignore").val(restoreMsg + ' (' + ignored + ')');
    }
    };

    function updateExtension(type, id, revision) {
    queuedManager.add({
    type: 'GET',
    dataType: 'json',
    url: ws_url,
    data: { method: 'pwg.extensions.update', type: type, id: id, revision: revision, pwg_token: pwg_token },
    success: function(data) {
    if (data['stat'] == 'ok') {
    jQuery.jGrowl( data['result'], { theme: 'success', header: successHead, life: 4000, sticky: false });
    jQuery("#"+type+"_"+id).remove();
    checkFieldsets();
    } else {
    jQuery.jGrowl( data['result'], { theme: 'error', header: errorHead, sticky: true });
    }
    },
    error: function(data) {
    jQuery.jGrowl( errorMsg, { theme: 'error', header: errorHead, sticky: true });
    }
    });
    };

    function ignoreExtension(type, id) {
    queuedManager.add({
    type: 'GET',
    url: ws_url,
    dataType: 'json',
    data: { method: 'pwg.extensions.ignoreUpdate', type: type, id: id, pwg_token: pwg_token },
    success: function(data) {
    if (data['stat'] == 'ok') {
    jQuery("#"+type+"_"+id).hide();
    jQuery("#reset_ignore").show();
    checkFieldsets();
    }
    }
    });
    };

    function autoupdate_bar_toggle(i) {
    todo = todo + i;
    if ((i == 1 && todo == 1) || (i == -1 && todo == 0))
    jQuery('.autoupdate_bar').toggle();
    }

    jQuery(document).ready(function() {
    jQuery("td[id^='desc_'], p[id^='revdesc_']").click(function() {
    id = this.id.split('_');
    jQuery("#revdesc_"+id[1]).toggle('blind');
    jQuery(".button_"+id[1]).toggle();
    return false;
    });
    });

    checkFieldsets();
    {/literal}
    {/footer_script}

    <div class="autoupdate_bar">
	<button type="button" class="btn btn-submit" id="update_all" onClick="updateAll(); return false;">{'Update All'|translate}</button>
	<button type="button" class="btn btn-warning" id="ignore_all" onClick="ignoreAll(); return false;">{'Ignore All'|translate}</button>
	{if $SHOW_RESET}
	    <button type="button" class="btn btn-warning" id="reset_ignore" onClick="resetIgnored(); return false;" >{'Reset ignored updates'|translate}</button>
	{/if}
    </div>
    <div class="autoupdate_bar d-none">
	{'Please wait...'|translate}
    </div>

    <p id="up_to_date" style="display:none; text-align:left; margin-left:20px;">{'All %s are up to date.'|@sprintf:$EXT_TYPE|translate}</p>

    {if !empty($update_languages)}
	<h3>{'Languages'|translate}</h3>
	<div class="extensions">
	    {foreach $update_languages as $language}
		<div class="extension row{if $language.IGNORED} d-none{/if}" id="languages_{$language.EXT_ID}">
		    <div class="col-2">
			<div>{$language.EXT_NAME}</div>
			<div>{'Version'|translate} {$language.CURRENT_VERSION}</div>
		    </div>
		    <div class="col-10">
			<button type="button" class="btn btn-sm btn-submit" onClick="updateExtension('languages', '{$language.EXT_ID}', {$language.REVISION_ID});">{'Install'|translate}</button>
			<a class="btn btn-sm btn-success" href="{$language.URL_DOWNLOAD}">{'Download'|translate}</a>
			<button type="button" class="btn btn-sm btn-warning" onClick="ignoreExtension('languages', '{$language.EXT_ID}'); return false;">{'Ignore this update'|translate}</button>

			<div class="extension description" id="desc_{$language.ID}">
			    <em>{'Downloads'|translate}: {$language.DOWNLOADS}</em>
			    <button type="button" class="btn btn-link show-description" data-target="#description-{$language.EXT_ID}" data-toggle="collapse"><i class="fa fa-plus-square-o"></i></button>
			    {'New Version'|translate} : {$language.NEW_VERSION} | {'By %s'|translate:$language.AUTHOR}
			</div>
			<div class="revision description collapse" id="description-{$language.EXT_ID}">
			    <p>{$language.EXT_DESC|@htmlspecialchars|@nl2br}</p>
			    <hr>
			    {$language.REV_DESC|@htmlspecialchars|@nl2br}
			</div>
		    </div>
		</div>
	    {/foreach}
	</div>
    {/if}
{/block}
