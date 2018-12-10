{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Plugins'|translate}</a></li>
    <li class="breadcrumb-item">{'Other plugins'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='jquery.sort' load='footer' path='admin/theme/js/plugins/jquery.sort.js'}

    {footer_script require='jquery.ui.effect-blind,jquery.sort'}{literal}
    var sortOrder = 'date';
    var sortPlugins = (function(a, b) {
    if (sortOrder == 'downloads' || sortOrder == 'revision' || sortOrder == 'date')
    return parseInt($(a).find('input[name="'+sortOrder+'"]').val())
    < parseInt($(b).find('input[name="'+sortOrder+'"]').val()) ? 1 : -1;
    else
    return $(a).find('input[name="'+sortOrder+'"]').val().toLowerCase()
    > $(b).find('input[name="'+sortOrder+'"]').val().toLowerCase()  ? 1 : -1;
    });

    $(function(){
    $("td[id^='desc_']").click(function() {
    id = this.id.split('_');
    nb_lines = $("#bigdesc_"+id[1]).html().split('<br>').length;

    $("#smalldesc_"+id[1]).toggle('blind', 1);
    if ($(this).hasClass('bigdesc')) {
    $("#bigdesc_"+id[1]).toggle('blind', 1);
    } else {
    $("#bigdesc_"+id[1]).toggle('blind', 50 + (nb_lines * 30));
    }
    $(this).toggleClass('bigdesc');
    return false;
    });

    $('select[name="selectOrder"]').change(function() {
    sortOrder = this.value;
    $('.pluginBox').sortElements(sortPlugins);
    $.get("./index.php?plugins_new_order="+sortOrder);
    });

    $('#filter').keyup(function(){
    var filter = $(this).val();
    if (filter.length>2) {
    $('.pluginBox').hide();
    $('#availablePlugins .pluginBox input[name="name"]').each(function(){
    if ($(this).val().toUpperCase().indexOf(filter.toUpperCase()) != -1) {
    $(this).parents('div').show();
    }
    });
    }
    else {
    $('.pluginBox').show();
    }
    });
    $("#filter").focus();
    $(".titrePage input[name='Clear']").click(function(){
    $("#filter").val('');
    $(".pluginBox").show();
    });
    });
    {/literal}{/footer_script}

    {if not empty($plugins)}
	<p class="sort">
	    {'Sort order'|translate} :
	    {html_options class="custom-select" name="selectOrder" options=$order_options selected=$order_selected}
	</p>
    {/if}

    {if not empty($plugins)}
	<div id="availablePlugins">
	    <div class="fieldset">
		{foreach $plugins as $plugin}
		    <div class="plugin" id="plugin_{$plugin.ID}">
			<input type="hidden" name="date" value="{$plugin.ID}">
			<input type="hidden" name="name" value="{$plugin.EXT_NAME}">
			<input type="hidden" name="revision" value="{$plugin.REVISION_DATE}">
			<input type="hidden" name="downloads" value="{$plugin.DOWNLOADS}">
			<input type="hidden" name="author" value="{$plugin.AUTHOR}">
			<table>
			    <tr>
				<td class="pluginBoxNameCell">{$plugin.EXT_NAME}</td>
				{if $plugin.BIG_DESC != $plugin.SMALL_DESC}
				    <td id="desc_{$plugin.ID}" class="pluginDesc">
					<span id="smalldesc_{$plugin.ID}">
					    <img src="./theme/icon/plus.gif" alt="">{$plugin.SMALL_DESC}...
					</span>
					<span id="bigdesc_{$plugin.ID}" style="display:none;">
					    <img src="./theme/icon/minus.gif" alt="">{$plugin.BIG_DESC|@nl2br}<br>&nbsp;
					</span>
				    </td>
				{else}
				    <td>{$plugin.BIG_DESC|@nl2br}</td>
				{/if}
			    </tr>
			    <tr>
				<td>
				    <a href="{$plugin.URL_INSTALL}" onclick="return confirm('{'Are you sure you want to install this plugin?'|translate|@escape:javascript}');">{'Install'|translate}</a>
				    |  <a href="{$plugin.URL_DOWNLOAD}">{'Download'|translate}</a>
				</td>
				<td>
				    <em>{'Downloads'|translate}: {$plugin.DOWNLOADS}</em>
				    {'Version'|translate} {$plugin.VERSION}
				    | {'By %s'|translate:$plugin.AUTHOR}
				    | <a class="externalLink" href="{$plugin.EXT_URL}">{'Visit plugin site'|translate}</a>
				</td>
			    </tr>
			</table>
		    </div>
		{/foreach}
	    </div>
	</div>
    {else}
	<p>{'There is no other plugin available.'|translate}</p>
    {/if}
{/block}
