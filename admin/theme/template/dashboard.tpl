{extends file="__layout.tpl"}

{block name="footer_assets" prepend}
    <script>
     var ws_url = '{$ws}';
     var phyxo_need_update_msg = '<a href="{$U_UPDATE}">{'A new version of Phyxo is available.'|translate|escape:"javascript"}</a>';
     var ext_need_update_msg = '<a href="{$U_UPDATE_EXTENSIONS}">{'Some upgrades are available for extensions.'|translate|escape:"javascript"}</a>';
     var phyxo_is_uptodate_msg = "{'You are running the latest version of Phyxo.'|translate}";
    </script>
{/block}

{block name="content"}
    <h2>{'Phyxo Administration'|translate}</h2>
    <dl>
	<dt>{'Phyxo version'|translate}</dt>
	<dd>
	    <ul>
		<li>
		    <a href="{$PHPWG_URL}" class="externalLink">Phyxo</a> {$PWG_VERSION}&nbsp;
		    {if !$DEV}
			<span>(<a id="check-upgrade" href="{$U_CHECK_UPGRADE}">{'Check for upgrade'|translate}</a>)</span>
		    {/if}

		</li>
	    </ul>
	</dd>

	<dt>{'Environment'|translate}</dt>
	<dd>
	    <ul>
		<li>{'Operating system'|translate}: {$OS}</li>
		<li>PHP: {$PHP_VERSION} [{$PHP_DATATIME}]</li>
		<li>{$DB_ENGINE}: {$DB_VERSION} [{$DB_DATATIME}]</li>
		{if isset($GRAPHICS_LIBRARY)}
		    <li>{'Graphics Library'|translate}: {$GRAPHICS_LIBRARY}</li>
		{/if}
	    </ul>
	</dd>

	<dt>{'Database'|translate}</dt>
	<dd>
	    <ul>
		<li>
		    {$DB_ELEMENTS}
		    {if isset($first_added)}
			({$first_added})
		    {/if}
		</li>
		<li>{$DB_CATEGORIES} {$PHYSICAL_CATEGORIES} {'and'|translate} {$VIRTUAL_CATEGORIES} ({$DB_IMAGE_CATEGORY})</li>
		<li>{$DB_TAGS} ({$DB_IMAGE_TAG})</li>
		<li>{$DB_USERS}</li>
		<li>{$DB_GROUPS}</li>
		{if isset($DB_COMMENTS)}
		    <li>
			{$DB_COMMENTS}{if !empty($NB_PENDING_COMMENTS)} (<a href="{$U_PENDING_COMMENTS}">{'%d waiting for validation'|translate:$NB_PENDING_COMMENTS}</a>){/if}
		    </li>
		{/if}
		<li>{$DB_RATES}</li>
	    </ul>
	</dd>
    </dl>

    {* if $ENABLE_SYNCHRONIZATION}
	<form name="QuickSynchro" action="{$U_CAT_UPDATE}" method="post" id="QuickSynchro" style="display: block; text-align:right;">
	    <div>
		<input type="hidden" name="sync" value="files" checked="checked">
		<input type="hidden" name="display_info" value="1" checked="checked">
		<input type="hidden" name="add_to_caddie" value="1" checked="checked">
		<input type="hidden" name="privacy_level" value="0" checked="checked">
		<input type="hidden" name="sync_meta" checked="checked">
		<input type="hidden" name="simulate" value="0">
		<input type="hidden" name="subcats-included" value="1" checked="checked">
	    </div>
	    <div class="bigbutton">
		<span class="bigtext">{'Quick Local Synchronization'|translate}</span>
		<input type="submit" value="{'Quick Local Synchronization'|translate}" name="submit">
	    </div>
	</form>
    {/if *}
{/block}
