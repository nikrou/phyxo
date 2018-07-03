{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Plugins'|translate}</a></li>
    <li class="breadcrumb-item">{'Plugin list'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='jquery.ajaxmanager' load='footer' require='jquery' path='admin/theme/js/plugins/jquery.ajaxmanager.js' }
    {combine_script id='jquery.plugins.installed' load='footer' require='jquery.ajaxmanager' path='admin/theme/js/plugins_installed.js' }

    {footer_script require='jquery.ajaxmanager'}
    /* incompatible message */
    var incompatible_msg = '{'WARNING! This plugin does not seem to be compatible with this version of Phyxo.'|translate|@escape:'javascript'}';
    var activate_msg = '\n{'Do you want to activate anyway?'|translate|@escape:'javascript'}';
    var pwg_token = '{$PWG_TOKEN}';
    var confirmMsg  = '{'Are you sure?'|translate|@escape:'javascript'}';
    var show_details = {if $show_details}true{else}false{/if};
    {/footer_script}

    <div class="showDetails">
	{if $show_details}
	    <a class="btn btn-submit" href="{$base_url}&amp;show_details=0">{'hide details'|translate}</a>
	{else}
	    <a class="btn btn-submit" href="{$base_url}&amp;show_details=1">{'show details'|translate}</a>
	{/if}
    </div>

    {if isset($plugins)}

	{assign var='field_name' value='null'} {* <!-- 'counter' for fieldset management --> *}
	{counter start=0 assign=i} {* <!-- counter for 'deactivate all' link --> *}
	{foreach $plugins as $plugin}

	    {if $field_name != $plugin.STATE}
		{if $field_name != 'null'}
		  </div>
		{/if}

		<div class="plugins {$plugin.STATE}">
		    <h3>
			{if $plugin.STATE == 'active'}
			    {'Active Plugins'|translate}
			{elseif $plugin.STATE == 'inactive'}
			    {'Inactive Plugins'|translate}
			{elseif $plugin.STATE == 'missing'}
			    {'Missing Plugins'|translate}
			{elseif $plugin.STATE == 'merged'}
			    {'Obsolete Plugins'|translate}
			{/if}
		    </h3>
		    {assign var='field_name' value=$plugin.STATE}
	    {/if}

	    {if not empty($plugin.AUTHOR)}
		{if not empty($plugin.AUTHOR_URL)}
		    {assign var='author' value="<a href='%s'>%s</a>"|@sprintf:$plugin.AUTHOR_URL:$plugin.AUTHOR}
		{else}
		    {assign var='author' value='<u>'|cat:$plugin.AUTHOR|cat:'</u>'}
		{/if}
	    {/if}

	    <div id="{$plugin.ID}" class="plugin {$plugin.STATE}{if !$show_details} no-details{/if}">
		<div class="plugin-name">
		    {$plugin.NAME}
		</div>
		{if $show_details}
		    <div class="description">{$plugin.DESC}</div>
		{/if}
		<div class="actions">
		    {if $plugin.STATE == 'active'}
			<a href="{$plugin.U_ACTION}&amp;action=deactivate">{'Deactivate'|translate}</a>
			| <a href="{$plugin.U_ACTION}&amp;action=restore" class="plugin-restore"
			     title="{'Restore default configuration. You will lose your plugin settings!'|translate}" onclick="return confirm(confirmMsg);">{'Restore'|translate}</a>

		    {elseif $plugin.STATE == 'inactive'}
			<a href="{$plugin.U_ACTION}&amp;action=activate" class="activate">{'Activate'|translate}</a>
			| <a href="{$plugin.U_ACTION}&amp;action=delete" onclick="return confirm(confirmMsg);">{'Delete'|translate}</a>

		    {elseif $plugin.STATE == 'missing'}
			<a href="{$plugin.U_ACTION}&amp;action=uninstall" onclick="return confirm(confirmMsg);">{'Uninstall'|translate}</a>

		    {elseif $plugin.STATE == 'merged'}
			<a href="{$plugin.U_ACTION}&amp;action=delete">{'Delete'|translate}</a>
		    {/if}
		</div>
		<div class="version">
		    {'Version'|translate} {$plugin.VERSION}
		</div>
		<div class="author">
		    {if not empty($author)}
			| {'By %s'|translate:$author}
		    {/if}

		    {if not empty($plugin.VISIT_URL)}
			| <a class="externalLink" href="{$plugin.VISIT_URL}">{'Visit plugin site'|translate}</a>
		    {/if}
		</div>
	    </div> {*<!-- pluginBox -->*}


    {if $plugin.STATE == 'active'}
	{counter}
	{if $active_plugins == $i}
	    <p><button type="button" id="deactivate-all" class="btn btn-submit">{'Deactivate all'|translate}</button></p>
	    {counter}
	{/if}
    {/if}

	{/foreach}
    {/if}
{/block}
