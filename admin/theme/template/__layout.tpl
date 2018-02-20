<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	<meta http-equiv="Content-Type" content="text/html; charset={$CONTENT_ENCODING}">
	<meta name="generator" content="Phyxo, see https://www.phyxo.net">
	<title>{$GALLERY_TITLE} :: {$PAGE_TITLE}</title>
	<link rel="shortcut icon" type="image/x-icon" href="./favicon.ico">

	<link rel="stylesheet" href="{asset manifest='theme/build/manifest.json' src='app.css'}">

	{combine_script id='jquery' path='admin/theme/js/jquery/jquery.js'}
	{combine_script id='jquery.migrate' path='admin/theme/js/jquery/jquery-migrate-1.2.1.js'}

	<!-- BEGIN get_combined -->
	{get_combined_css}

	{get_combined_scripts load='header'}
	<!-- END get_combined -->

	{if not empty($head_elements)}
	    {foreach from=$head_elements item=elt}
		{$elt}
	    {/foreach}
	{/if}
    </head>
    <body id="{$BODY_ID}">
	<div id="the_page">
	    {if not empty($header_msgs)}
		<div class="header_msgs">
		    {foreach from=$header_msgs item=elt}
			{$elt}
		    {/foreach}
		</div>
	    {/if}

	    <div id="pwgHead">
		<h1>
		    <a href="{$U_ADMIN}" title="{'Visit Gallery'|translate}" class="tiptip">
			<span class="icon-home" style="font-size:larger"></span>
			{$GALLERY_TITLE}
		    </a>
		</h1>

		<div id="headActions">
		    {'Hello'|translate} {$USERNAME} |
		    <a class="icon-eye" href="{$U_RETURN}">{'Visit Gallery'|translate}</a> |
		    <a class="icon-help-circled" href="{$U_FAQ}" title="{'Instructions to use Phyxo'|translate}">{'Help Me'|translate}</a> |
		    <a class="icon-logout" href="{$U_LOGOUT}">{'Logout'|translate}</a>
		</div>
	    </div>

	    <div style="clear:both;"></div>

	    {if not empty($header_notes)}
		<div class="header_notes">
		    {foreach from=$header_notes item=elt}
			{$elt}
		    {/foreach}
		</div>
	    {/if}
	    <div id="pwgMain">
		{include file="_menubar.tpl"}

		<div id="content" class="content">
		    {if isset($TABSHEET)}
			{$TABSHEET}
		    {/if}
		    {if isset($U_HELP)}
			{combine_script id='core.scripts' load='async' path='admin/theme/js/scripts.js'}
			<ul class="HelpActions">
			    <li><a href="{$U_HELP}" onclick="popuphelp(this.href); return false;" title="{'Help'|translate}"><img src="./theme/icon/help.png" alt="(?)"></a></li>
			</ul>
		    {/if}

		    {if isset($errors)}
			<div class="errors">
			    <ul>
				{foreach from=$errors item=error}
				    <li>{$error}</li>
				{/foreach}
			    </ul>
			</div>
		    {/if}

		    {if isset($infos)}
			<div class="infos">
			    <ul>
				{foreach from=$infos item=info}
				    <li>{$info}</li>
				{/foreach}
			    </ul>
			</div>
		    {/if}

		    {if isset($warnings)}
			<div class="warnings">
			    <ul>
				{foreach from=$warnings item=warning}
				    <li>{$warning}</li>
				{/foreach}
			    </ul>
			</div>
		    {/if}

		    {block name="content"}{/block}
		</div>
	    </div>{* <!-- pwgMain --> *}

	    {if isset($footer_elements)}
		{foreach from=$footer_elements item=elt}
		    {$elt}
		{/foreach}
	    {/if}

	    {if isset($debug.QUERIES_LIST)}
		<div id="debug">
		    {foreach from=$debug.QUERIES_LIST item=query}
			{$query.sql}
		    {/foreach}
		</div>
	    {/if}

	    <div id="footer">
		<div id="piwigoInfos">
		    {* Please, do not remove this copyright. If you really want to,
		       contact us on http://www.phyxo.net/ to find a solution on how
		       to show the origin of the script...
		     *}

		    {'Powered by'|translate}
		    <a class="externalLink" href="{$PHPWG_URL}" title="{'Visit Phyxo project website'|translate}">Phyxo</a>
		    {$VERSION}
		</div>

		<div id="pageInfos">
		    {if isset($debug.TIME) }
			{'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
		    {/if}

		    {'Contact'|translate}
		    <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
		</div>{* <!-- pageInfos --> *}

	    </div>{* <!-- footer --> *}
	</div>{* <!-- the_page --> *}

	{combine_script id='jquery.tipTip' load='footer' path='admin/theme/js/plugins/jquery.tipTip.js'}
	{footer_script require='jquery.tipTip'}
	jQuery('.tiptip').tipTip({
	delay: 0,
	fadeIn: 200,
	fadeOut: 200
	});

	jQuery('a.externalLink').click(function() {
	window.open(jQuery(this).attr("href"));
	return false;
	});
        {/footer_script}

	<!-- BEGIN get_combined -->
	{get_combined_scripts load='footer'}
	<!-- END get_combined -->

	<script src="{asset manifest='theme/build/manifest.json' src='vendor.js'}"></script>
	<script src="{asset manifest='theme/treflez/build/manifest.json' src='app.js'}"></script>
    </body>
</html>
