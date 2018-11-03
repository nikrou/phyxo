<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	<meta http-equiv="Content-Type" content="text/html; charset={$CONTENT_ENCODING}">
	<meta name="generator" content="Phyxo, see https://www.phyxo.net">
	<title>{$GALLERY_TITLE} :: {$PAGE_TITLE}</title>
	<link rel="shortcut icon" type="image/x-icon" href="./favicon.ico">

	<link rel="stylesheet" href="{asset manifest='../../admin/theme/build/manifest.json' src='app.css'}">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<!-- BEGIN get_combined -->
	{get_combined_css}

	{get_combined_scripts load='header'}
	<!-- END get_combined -->

	{if not empty($head_elements)}
	    {foreach $head_elements as $elt}
		{$elt}
	    {/foreach}
	{/if}

	{block name="head_assets"}
	    <script src="./theme/js/jquery/jquery.js"></script>
	    <script src="./theme/js/jquery/jquery-migrate-1.2.1.js"></script>
	{/block}
    </head>
    <body>
	{block name="header"}
	    <header>
		<nav class="navbar navbar-expand-md navbar-dark">
		    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse">
			<span class="navbar-toggler-icon"></span>
		    </button>
		    <div class="collapse navbar-collapse" id="navbarCollapse">
			<a class="navbar-brand mr-auto" href="{$U_ADMIN}" title="{'Visit Gallery'|translate}"><i class="fa fa-home"></i> {$GALLERY_TITLE}</a>
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

		    {if isset($errors)}
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
			    {foreach $errors as $error}
				<p>{$error}</p>
			    {/foreach}
			    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<i class="fa fa-times"></i>
			    </button>
			</div>
		    {/if}

		    {if isset($infos)}
			<div class="alert alert-success alert-dismissible fade show" role="alert">
			    {foreach $infos as $info}
				<p>{$info}</p>
			    {/foreach}
			    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
				<i class="fa fa-times"></i>
			    </button>
			</div>
		    {/if}

		    {if isset($warnings)}
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

		{block name="aside"}
		    <aside role="navigation">
			{include file="_menubar.tpl"}
		    </aside>
		{/block}
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
	</div>

	<footer>
	    <div class="copyright">
		{'Powered by'|translate}
		<a class="external" href="{$PHPWG_URL}" title="{'Visit Phyxo project website'|translate}">Phyxo</a>
		{$VERSION}
	    </div>

	    <div id="page-infos">
		{if isset($debug.TIME) }
		    {'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
		{/if}

		{'Contact'|translate}
		<a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
	    </div>
	</footer>

	<!-- BEGIN get_combined -->
	{get_combined_scripts load='footer'}
	<!-- END get_combined -->

	{block name="footer_assets"}
	    <script src="{asset manifest='../../admin/theme/build/build/manifest.json' src='app.js'}"></script>
	{/block}
    </body>
</html>
