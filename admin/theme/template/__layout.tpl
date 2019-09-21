<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="generator" content="Phyxo, see https://www.phyxo.net">
	<title>{$GALLERY_TITLE} :: {$PAGE_TITLE}</title>
	<link rel="stylesheet" href="{$ROOT_URL}{asset manifest='./build/manifest.json' src='app.css'}">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	{get_combined_css}
	{get_combined_scripts load='header'}

	{if !empty($head_elements)}
	    {foreach $head_elements as $elt}
		{$elt}
	    {/foreach}
	{/if}

	{block name="head_assets"}
	    <script src="{$ROOT_URL}admin/theme/js/jquery/jquery.js"></script>
	    <script src="{$ROOT_URL}admin/theme/js/jquery/jquery-migrate-1.2.1.js"></script>
	{/block}
    </head>
    <body>
	{block name="header"}
	    <header>
		<nav class="navbar navbar-expand-lg navbar-dark">
		    <a class="navbar-brand mr-auto" href="{$U_ADMIN}" title="{'Administration'|translate}"><i class="fa fa-home"></i> {$GALLERY_TITLE}</a>
		    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse">
			<i class="fa fa-align-justify"></i>
		    </button>

		    <div class="collapse navbar-collapse" id="navbarCollapse">
			<ul class="navbar-nav ml-auto">
			    <li class="nav-item"><a class="nav-link" href="{$U_RETURN}"><i class="fa fa-eye"></i> {'Visit Gallery'|translate}</a></li>
			    <li class="nav-item"><a class="nav-link" href="{$U_LOGOUT}"><i class="fa fa-sign-out"></i> {'Logout'|translate}  ({$USERNAME})</a></li>
			</ul>
		    </div>
		</nav>

		{if !empty($header_msgs)}
		    <div class="alert alert-dark" role="alert">
			{foreach $header_msgs as $elt}
			    {$elt}
			{/foreach}
		    </div>
		{/if}

		{if not empty($header_notes)}
		    <div class="alert alert-warning" role="alert">
			{foreach $header_notes as $elt}
			    {$elt}
			{/foreach}
		    </div>
		{/if}
	    </header>
	{/block}

	<div class="wrapper">
	    <main>
		<section role="content">
		    {block name="menu_toggle_button"}
			<p>
			    <button type="button" id="sidebarCollapse" class="btn btn-info">
				<i class="fa fa-align-left"></i>
				<span>{'Toggle menu'|translate}</span>
			    </button>
			</p>
		    {/block}

		    {block name="breadcrumb"}
			<nav aria-label="breadcrumb">
			    <ol class="breadcrumb">
				<li class="breadcrumb-item active" aria-current="page"><a href="{$U_ADMIN}">{'Home'|translate}</a></li>
				{block name="breadcrumb-items"}{/block}
			    </ol>
			</nav>
		    {/block}

		    {block name="tabs"}
			{if !empty($tabsheet)}
			    <ul class="nav nav-tabs">
				{foreach $tabsheet as $name => $tab}
				    <li class="nav-item">
					<a class="nav-link{if $tab.selected} active{/if}" href="{$tab.url}">{$tab.caption}</a>
				    </li>
				{/foreach}
			    </ul>
			{/if}
		    {/block}

		    {if !empty($errors)}
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
			    {foreach $errors as $error}
				<p>{$error}</p>
			    {/foreach}
			    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<i class="fa fa-times"></i>
			    </button>
			</div>
		    {/if}

		    <div class="alert alert-dismissible fade hide" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close">
			    <i class="fa fa-times"></i>
			</button>
		    </div>

		    {if !empty($infos)}
			<div class="alert alert-success alert-dismissible fade show" role="alert">
			    {foreach $infos as $info}
				<p>{$info}</p>
			    {/foreach}
			    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<i class="fa fa-times"></i>
			    </button>
			</div>
		    {/if}

		    {if !empty($warnings)}
			<div class="alert alert-warning alert-dismissible fade show" role="alert">
			    {foreach $warnings as $warning}
				<p>{$warning}</p>
			    {/foreach}
			    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<i class="fa fa-times"></i>
			    </button>
			</div>
		    {/if}

		    {block name="content"}{/block}
		</section>
	    </main>

	    {block name="aside"}
		<aside id="sidebar" role="navigation">
		    {include file="_menubar.tpl"}
		</aside>
	    {/block}
	</div>

	<footer>
	    <div class="copyright">
		{'Powered by'|translate}&nbsp;<a href="{$PHYXO_URL}">Phyxo</a>&nbsp;{$PHYXO_VERSION}
	    </div>

	    <div id="page-infos">
		{if isset($debug.TIME) }
		    {'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
		{/if}

		{'Contact'|translate}
		<a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
	    </div>
	</footer>

	{get_combined_scripts load='footer'}

	{block name="footer_assets"}
	    <script src="{asset manifest='./build/manifest.json' src='app.js'}"></script>
	{/block}
    </body>
</html>
