{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item">{'Upgrade'|translate}</li>
{/block}

{block name="content"}
    {if $DEV_VERSION}
	<p>{'You are running on development sources, upgrade is made with <a href="./?page=upgrade_feed">upgrade feed</a>.'|translate}</p>
    {else}
	{if $no_upgrade_required}
	    <p>{'No upgrade required, the database structure is up to date'|translate}</p>
	    <p><a href="{$ROOT_URL}">{'back to gallery'|translate}</a></p>
	{elseif isset($introduction)}
	    <h2>{'Version'|translate} {$RELEASE} - {'Upgrade'|translate}</h2>

	    {if isset($errors)}
		<div class="errors">
		    <ul>
			{foreach $errors as $error}
			    <li>{$error}</li>
			{/foreach}
		    </ul>
		</div>
	    {/if}

	    <form method="POST" action="{$introduction.F_ACTION}&amp;language={$LANGUAGE}" name="upgrade_form">
		<div class="fieldset">
		    <table>
			<tr>
			    <td>{'Language'|translate}</td>
			    <td>
				<select class="custom-select" name="language" onchange="document.location = '{$introduction.F_ACTION}&language='+this.options[this.selectedIndex].value;">
				    {html_options options=$language_options selected=$language_selection}
				</select>
			    </td>
			</tr>
		    </table>

		    <p>{'This page proposes to upgrade your database corresponding to your old version of Phyxo to the current version. The upgrade assistant thinks you are currently running a <strong>release %s</strong> (or equivalent).'|translate:$introduction.CURRENT_RELEASE}</p>
		    {if isset($login)}
			<p>{'Only administrator can run upgrade: please sign in below.'|translate}</p>
		    {/if}
		</div>
		<p>
		    <input class="btn btn-submit" type="submit" name="submit" value="{'Upgrade from version %s to %s'|translate:$introduction.CURRENT_RELEASE:$RELEASE}">
		</p>
	    </form>
	{/if}

	{if isset($upgrade)}
	    <h2>{'Upgrade from version %s to %s'|translate:$upgrade.VERSION:$RELEASE}</h2>

	    <div class="fieldset">
		<h3>{'Statistics'|translate}</h3>
		<ul>
		    <li>{'total upgrade time'|translate} : {$upgrade.TOTAL_TIME}</li>
		    <li>{'total SQL time'|translate} : {$upgrade.SQL_TIME}</li>
		    <li>{'SQL queries'|translate} : {$upgrade.NB_QUERIES}</li>
		</ul>
	    </div>

	    <div class="fieldset">
		<h3>{'Upgrade informations'|translate}</h3>
		<ul>
		    {foreach from=$infos item=info}
			<li>{$info}</li>
		    {/foreach}
		</ul>
	    </div>

	    <p>
		<a href="index.php">{'Home'|translate}</a>
	    </p>
	{/if}
    {/if}
{/block}
