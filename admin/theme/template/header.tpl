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
