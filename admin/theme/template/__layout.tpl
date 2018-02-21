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
	    {foreach $head_elements as $elt}
		{$elt}
	    {/foreach}
	{/if}
    </head>
    <body id="{$BODY_ID}">
	<div id="the_page">
	    {if not empty($header_msgs)}
		<div class="header_msgs">
		    {foreach $header_msgs as $elt}
			{$elt}
		    {/foreach}
		</div>
	    {/if}

	    <div id="pwgHead">
		<a href="{$U_ADMIN}" title="{'Visit Gallery'|translate}" class="tiptip">
		    <i class="fa fa-home"></i> {$GALLERY_TITLE}
		</a>

		<div id="headActions">
		    {'Hello'|translate} {$USERNAME} |
		    <a class="fa fa-eye" href="{$U_RETURN}">{'Visit Gallery'|translate}</a> |
		    <a class="fa fa-sign-out" href="{$U_LOGOUT}">{'Logout'|translate}</a>
		</div>
	    </div>

	    <div style="clear:both;"></div>

	    {if not empty($header_notes)}
		<div class="header_notes">
		    {foreach $header_notes as $elt}
			{$elt}
		    {/foreach}
		</div>
	    {/if}
	    <main id="pwgMain">
		<section role="content" id="content" class="content">
		    {if isset($TABSHEET)}
			{$TABSHEET}
		    {/if}

		    {if isset($errors)}
			<div class="errors">
			    <ul>
				{foreach $errors as $error}
				    <li>{$error}</li>
				{/foreach}
			    </ul>
			</div>
		    {/if}

		    {if isset($infos)}
			<div class="infos">
			    <ul>
				{foreach $infos as $info}
				    <li>{$info}</li>
				{/foreach}
			    </ul>
			</div>
		    {/if}

		    {if isset($warnings)}
			<div class="warnings">
			    <ul>
				{foreach $warnings as $warning}
				    <li>{$warning}</li>
				{/foreach}
			    </ul>
			</div>
		    {/if}

		    {block name="content"}{/block}
		</section>

		<aside role="navigation">
		    {include file="_menubar.tpl"}
		</aside>
	    </main>

	    {if isset($footer_elements)}
		{foreach $footer_elements as $elt}
		    {$elt}
		{/foreach}
	    {/if}

	    {if isset($debug.QUERIES_LIST)}
		<div id="debug">
		    {foreach $debug.QUERIES_LIST as $query}
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

	<!-- BEGIN get_combined -->
	{get_combined_scripts load='footer'}
	<!-- END get_combined -->

	<script src="{asset manifest='theme/build/manifest.json' src='vendor.js'}"></script>
	<script src="{asset manifest='theme/build/build/manifest.json' src='app.js'}"></script>
    </body>
</html>
