<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	<meta charset="{$CONTENT_ENCODING}">
	<meta name="generator" content="Phyxo, see https://www.phyxo.net/">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	{if $meta_ref_enabled}
	    {if isset($INFO_AUTHOR)}
		<meta name="author" content="{$INFO_AUTHOR|@strip_tags:false|@replace:'"':' '}">
	    {/if}
	    {if isset($related_tags)}
		<meta name="keywords" content="{foreach from=$related_tags item=tag name=tag_loop}{if !$smarty.foreach.tag_loop.first}, {/if}{$tag.name}{/foreach}">
	    {/if}
	    {if isset($COMMENT_IMG)}
		<meta name="description" content="{$COMMENT_IMG|@strip_tags:false|@replace:'"':' '}{if isset($INFO_FILE)} - {$INFO_FILE}{/if}">
	    {else}
		<meta name="description" content="{$PAGE_TITLE}{if isset($INFO_FILE)} - {$INFO_FILE}{/if}">
	    {/if}
	{/if}

	<title>{if $PAGE_TITLE!=\Phyxo\Functions\Language::l10n('Home') && $PAGE_TITLE!=$GALLERY_TITLE}{$PAGE_TITLE} | {/if}{$GALLERY_TITLE}</title>
	<link rel="start" title="{'Home'|translate}" href="{$U_HOME}" >
	<link rel="search" title="{'Search'|translate}" href="{$ROOT_URL}search.php">
	{if isset($first.U_IMG)}
	    <link rel="first" title="{'First'|translate}" href="{$first.U_IMG}">
	{/if}
	{if isset($previous.U_IMG)}
	    <link rel="prev" title="{'Previous'|translate}" href="{$previous.U_IMG}">
	{/if}
	{if isset($next.U_IMG)}
	    <link rel="next" title="{'Next'|translate}" href="{$next.U_IMG}">
	{/if}
	{if isset($last.U_IMG)}
	    <link rel="last" title="{'Last'|translate}" href="{$last.U_IMG}">
	{/if}
	{if isset($U_UP)}
	    <link rel="up" title="{'Thumbnails'|translate}" href="{$U_UP}">
	{/if}
	{if isset($U_PREFETCH)}
	    <link rel="prefetch" href="{$U_PREFETCH}">
	{/if}
	{if isset($U_CANONICAL)}
	    <link rel="canonical" href="{$U_CANONICAL}">
	{/if}

	{block name="head_assets"}
	    <!-- head_assets (LAYOUT) -->
	    <link rel="stylesheet" href="{asset manifest='./themes/treflez/build/manifest.json' src='theme.css'}">
	    {if file_exists("local/treflez/custom.css")}
		<link rel="stylesheet" type="text/css" href="{$ROOT_URL}local/treflez/custom.css">
	    {/if}
	    <script>
	     var phyxo_root_url = "{$ROOT_URL}";
	     var phyxo_cookie_path = "{$COOKIE_PATH}";
	    </script>
	    {if not empty($head_elements)}
		{foreach $head_elements as $elt}
		    {$elt}
		{/foreach}
	    {/if}
	    <!-- /head_assets (LAYOUT) -->
	{/block}
    </head>
    <body>
	<div id="wrapper">
	    {if isset($MENUBAR)}
		<nav class="navbar navbar-expand-lg navbar-main {$theme_config->navbar_main_bg} {if $theme_config->page_header == 'fancy'}navbar-dark navbar-transparent fixed-top{else}{$theme_config->navbar_main_style}{/if}">
		    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
			{if $theme_config->logo_image_enabled && $theme_config->logo_image_path !== ''}
			    <a class="navbar-brand mr-auto" href="{$U_HOME}"><img class="img-fluid" src="{$ROOT_URL}{$theme_config->logo_image_path}" alt="{$GALLERY_TITLE}"/></a>
			{else}
			    <a class="navbar-brand mr-auto" href="{$U_HOME}">{$GALLERY_TITLE}</a>
			{/if}
			<button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbar-menubar" aria-controls="navbar-menubar" aria-expanded="false" aria-label="Toggle navigation">
			    <span class="fa fa-bars"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbar-menubar">
			    {if $theme_config->quicksearch_navbar}
				<form class="form-inline navbar-form ml-auto" role="search" action="{$ROOT_URL}qsearch.php" method="get" id="quicksearch" onsubmit="return this.q.value!='' && this.q.value!=qsearch_prompt;">
				    <i class="fa fa-search" title="{'Search'|translate}" aria-hidden="true"></i>
				    <div class="form-group">
					<input type="text" name="q" id="qsearchInput" class="form-control" placeholder="{'Search'|translate}" />
				    </div>
				</form>
			    {/if}
			    {block name="menubar"}
				{include file="menubar.tpl"}
			    {/block}
			</div>
		    </div>
		</nav>
	    {/if}

	    {if !isset($slideshow) && !empty($MENUBAR)}
		{if $theme_config->page_header == 'jumbotron'}
		    <div class="jumbotron mb-0">
			<div class="container{if $theme_config->fluid_width}-fluid{/if}">
			    <div id="theHeader">{$PAGE_BANNER}</div>
			</div>
		    </div>
		{elseif $theme_config->page_header == 'fancy'}
		    <div class="page-header{if !$theme_config->page_header_full || ($theme_config->page_header_full && !$is_homepage)} page-header-small{/if}">
			<div class="page-header-image" style="background-image: url({$theme_config->page_header_image}); transform: translate3d(0px, 0px, 0px);"></div>
			<div class="container">
			    <div id="theHeader" class="content-center">
				{$PAGE_BANNER}
			    </div>
			</div>
		    </div>
		{/if}
	    {/if}

	    {if not empty($header_msgs)}
		{foreach $header_msgs as $msg}
		{/foreach}
	    {/if}

	    {if not empty($header_notes)}
		{foreach $header_notes as $note}
		{/foreach}
	    {/if}

	    {block name="content"}{/block}

	    {if !empty($cats_navbar)}
		{include file='navigation_bar.tpl' navbar=$cats_navbar}
	    {/if}

	    {if !empty($thumb_navbar)}
		{include file='navigation_bar.tpl' navbar=$thumb_navbar}
	    {/if}

	    <div class="copyright container{if $theme_config->fluid_width}-fluid{/if}">
		<div class="text-center">
		    {if isset($debug.TIME)}
			{'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
		    {/if}
		    {'Powered by'|translate}	<a href="{$PHYXO_URL}">Phyxo</a>
		    {$VERSION}
		    {if isset($CONTACT_MAIL)}
			| <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|@escape:url}">{'Contact webmaster'|translate}</a>
		    {/if}

		    {if isset($footer_elements)}
			{foreach $footer_elements as $v}
			    {$v}
			{/foreach}
		    {/if}
		</div>
	    </div>
	</div>

	{block name="footer_assets"}
	    <!-- footer_assets (LAYOUT) -->
	    <script src="{asset manifest='./themes/treflez/build/manifest.json' src='theme.js'}"></script>
	    <!-- /footer_assets (LAYOUT) -->
        {/block}
    </body>
</html>
