{extends file="__layout.tpl"}

{block name="footer_assets" append}
    <script src="./theme/js/intro.js"></script>
    <script>
    var phyxo_need_update_msg = '<a href="./index.php?page=updates">{'A new version of Phyxo is available.'|translate|@escape:"javascript"}</a>';
    var ext_need_update_msg = '<a href="./index.php?page=updates&amp;tab=ext">{'Some upgrades are available for extensions.'|translate|@escape:"javascript"}</a>';
	var phyxo_is_uptodate_msg = "{'You are running the latest version of Phyxo.'|translate}";
    </script>
{/block}

{block name="content"}
    <h2>{'Phyxo Administration'|translate}</h2>
    <dl style="padding-top: 30px;">
	<dt>{'Phyxo version'|translate}</dt>
	<dd>
	    <ul>
		<li><a href="{$PHPWG_URL}" class="externalLink">Phyxo</a> {$PWG_VERSION}&nbsp;(<a id="check-upgrade" href="{$U_CHECK_UPGRADE}">{'Check for upgrade'|translate}</a>)</li>
	    </ul>
	</dd>

	<dt>{'Environment'|translate}</dt>
	<dd>
	    <ul>
		<li>{'Operating system'|translate}: {$OS}</li>
		<li>PHP: {$PHP_VERSION} (<a href="{$U_PHPINFO}" class="externalLink">{'Show info'|translate}</a>)  [{$PHP_DATATIME}]</li>
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
			({$first_added.DB_DATE})
		    {/if}
		</li>
		<li>{$DB_CATEGORIES} ({$DB_IMAGE_CATEGORY})</li>
		<li>{$DB_TAGS} ({$DB_IMAGE_TAG})</li>
		<li>{$DB_USERS}</li>
		<li>{$DB_GROUPS}</li>
		{if isset($DB_COMMENTS)}
		    <li>
			{$DB_COMMENTS}{if !empty($NB_PENDING_COMMENTS)} (<a href="{$U_COMMENTS}">{'%d waiting for validation'|translate:$NB_PENDING_COMMENTS}</a>){/if}
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
