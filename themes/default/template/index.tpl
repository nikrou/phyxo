<!DOCTYPE html>
<html>
    <head>
	<meta charset="UTF-8">
	{block name="head"}
	    <title>{if $PAGE_TITLE!=l10n('Home') && $PAGE_TITLE!=$GALLERY_TITLE}{$PAGE_TITLE} | {/if}{$GALLERY_TITLE}</title>
	    <meta name="generator" content="Phyxo, see https://www.phyxo.net/">
	    <meta name="viewport" content="width=device-width, initial-scale=1">
	    {if isset($meta_ref)}
	    	{if isset($INFO_AUTHOR)}
	    	    <meta name="author" content="{$INFO_AUTHOR|strip_tags:false|replace:'"':' '}">
	    	{/if}
	    	{if isset($related_tags)}
	    	    <meta name="keywords" content="{foreach from=$related_tags item=tag name=tag_loop}{if !$smarty.foreach.tag_loop.first}, {/if}{$tag.name}{/foreach}">
	    	{/if}
	    	{if isset($COMMENT_IMG)}
	    	    <meta name="description" content="{$COMMENT_IMG|strip_tags:false|replace:'"':' '}{if isset($INFO_FILE)} - {$INFO_FILE}{/if}">
	    	{else}
	    	    <meta name="description" content="{$PAGE_TITLE}{if isset($INFO_FILE)} - {$INFO_FILE}{/if}">
	    	{/if}
	    {/if}
	    <link rel="shortcut icon" type="image/x-icon" href="{$ROOT_URL}{$themeconf.icon_dir}/favicon.ico">
	    <link rel="start" title="{'Home'|translate}" href="{$U_HOME}" >
	    <link rel="search" title="{'Search'|translate}" href="{$ROOT_URL}search.php" >
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
	    {if not empty($page_refresh)}<meta http-equiv="refresh" content="{$page_refresh.TIME};url={$page_refresh.U_REFRESH}">{/if}
	    {foreach $themes as $theme}
	    	{if $theme.load_css}
		    <link rel="stylesheet" href="themes/{$theme.id}/css/style.css">
	    	{/if}
	    	{if !empty($theme.local_head)}
	    	    {include file=$theme.local_head load_css=$theme.load_css}
	    	{/if}
	    {/foreach}
	    {if not empty($head_elements)}
	    	{foreach $head_elements as $element}
	    	    {$element}
	    	{/foreach}
	    {/if}
	{/block}
	{block name="html_head"}{/block}
    </head>
    <body>
	<header>
	    {block name="header"}
		<div class="banner">{$PAGE_BANNER}</div>
	    {/block}
	</header>

	<main>
	    {block name="main"}
		{if not empty($header_notes)}
		    <div class="notes header">
			{foreach $header_notes as $element}
			    <p>{$element}</p>
			{/foreach}
		    </div>
		{/if}
		{if isset($errors) or isset($infos)}
		    <div class="content messages">
		    	{include file="infos_errors.tpl"}
		    </div>
		{/if}

		<section role="content">
		    <nav role="breadcrumb">
			{block name="breadcrumb"}
			    {if !empty($TITLE)}
				<h2>{$TITLE}</h2>
			    {/if}
			{/block}
		    </nav>
		    {if !empty($PLUGIN_INDEX_CONTENT_BEFORE)}{$PLUGIN_INDEX_CONTENT_BEFORE}{/if}
		    <div class="content">
			{if !empty($PLUGIN_INDEX_CONTENT_BEGIN)}{$PLUGIN_INDEX_CONTENT_BEGIN}{/if}
			{block name="content"}
			    {if !empty($no_search_results)}
				<p class="search_results">{'No results for'|translate} :
				    <em><strong>
					{foreach $no_search_results as $res}
					    {if !$res@first} &mdash; {/if}
					    {$res}
					{/foreach}
				    </strong></em>
				</p>
			    {/if}

			    {if !empty($category_search_results)}
				<p class="search_results">{'Album results for'|translate} <strong>{$QUERY_SEARCH}</strong> :
				    <em><strong>
					{foreach from=$category_search_results item=res name=res_loop}
					    {if !$smarty.foreach.res_loop.first} &mdash; {/if}
					    {$res}
					{/foreach}
				    </strong></em>
				</p>
			    {/if}

			    {if !empty($tag_search_results)}
				<p class="search_results">{'Tag results for'|translate} <strong>{$QUERY_SEARCH}</strong> :
				    <em><strong>
					{foreach from=$tag_search_results item=tag name=res_loop}
					    {if !$smarty.foreach.res_loop.first} &mdash; {/if} <a href="{$tag.URL}">{$tag.name}</a>
					{/foreach}
				    </strong></em>
				</p>
			    {/if}

			    {if isset($FILE_CHRONOLOGY_VIEW)}
				{include file=$FILE_CHRONOLOGY_VIEW}
			    {/if}

			    {if !empty($CONTENT_DESCRIPTION)}
				<div class="additional_info">
				    {$CONTENT_DESCRIPTION}
				</div>
			    {/if}

			    {if !empty($CONTENT)}{$CONTENT}{/if}

			    {if !empty($CATEGORIES)}{$CATEGORIES}{/if}

			    {if !empty($cats_navbar)}
				{include file="navigation_bar.tpl"|@get_extent:"navbar" navbar=$cats_navbar}
			    {/if}
			    {if !empty($THUMBNAILS)}
				<ul class="thumbnails" id="thumbnails">
				    {$THUMBNAILS}
				</ul>
			    {/if}
			    {if !empty($thumb_navbar)}
				{include file='navigation_bar.tpl'|@get_extent:'navbar' navbar=$thumb_navbar}
			    {/if}
			{/block}
			{if !empty($PLUGIN_INDEX_CONTENT_END)}{$PLUGIN_INDEX_CONTENT_END}{/if}
		    </div>
		</section>

		{block name="menubar"}
		    <aside role="navigation">
			{if isset($MENUBAR)}{$MENUBAR}{/if}
		    </aside>
		{/block}

		<aside role="context">
		    {block name="context"}
			<div class="titrePage{if isset($chronology.TITLE)} calendarTitleBar{/if}">
			    <ul class="categoryActions">
				{if !empty($image_orders)}
				    <li>
					<a id="sortOrderLink" title="{'Sort order'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					    <span class="pwg-icon pwg-icon-sort"></span><span class="pwg-button-text">{'Sort order'|translate}</span>
					</a>
					<div id="sortOrderBox" class="switchBox">
					    <div class="switchBoxTitle">{'Sort order'|translate}</div>
					    {foreach from=$image_orders item=image_order name=loop}{if !$smarty.foreach.loop.first}<br>{/if}
						{if $image_order.SELECTED}
						    <span>&#x2714; </span>{$image_order.DISPLAY}
						{else}
						    <span class="visually-hidden">&#x2714; </span><a href="{$image_order.URL}" rel="nofollow">{$image_order.DISPLAY}</a>
						{/if}
					    {/foreach}
					</div>
				    </li>
				{/if}
				{if !empty($image_derivatives)}
				    <li>
					<a id="derivativeSwitchLink" title="{'Photo sizes'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					    <span class="pwg-icon pwg-icon-sizes"></span><span class="pwg-button-text">{'Photo sizes'|translate}</span>
					</a>
					<div id="derivativeSwitchBox" class="switchBox">
					    <div class="switchBoxTitle">{'Photo sizes'|translate}</div>
					    {foreach from=$image_derivatives item=image_derivative name=loop}{if !$smarty.foreach.loop.first}<br>{/if}
						{if $image_derivative.SELECTED}
						    <span>&#x2714; </span>{$image_derivative.DISPLAY}
						{else}
						    <span class="visually-hidden">&#x2714; </span><a href="{$image_derivative.URL}" rel="nofollow">{$image_derivative.DISPLAY}</a>
						{/if}
					    {/foreach}
					</div>
				    </li>
				{/if}

				{if isset($favorite)}
				    <li id="cmdFavorite"><a href="{$favorite.U_FAVORITE}" title="{'delete all photos from your favorites'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					<span class="pwg-icon pwg-icon-favorite-del"></span><span class="pwg-button-text">{'delete all photos from your favorites'|translate}</span>
				    </a></li>
				{/if}
				{if isset($U_CADDIE)}
				    <li id="cmdCaddie"><a href="{$U_CADDIE}" title="{'Add to caddie'|translate}" class="pwg-state-default pwg-button">
					<span class="pwg-icon pwg-icon-caddie-add"></span><span class="pwg-button-text">{'Caddie'|translate}</span>
				    </a></li>
				{/if}
				{if isset($U_EDIT)}
				    <li id="cmdEditAlbum"><a href="{$U_EDIT}" title="{'Edit album'|translate}" class="pwg-state-default pwg-button">
					<span class="pwg-icon pwg-icon-category-edit"></span><span class="pwg-button-text">{'Edit'|translate}</span>
				    </a></li>
				{/if}
				{if isset($U_SEARCH_RULES)}
				    {combine_script id='core.scripts' load='async' path='themes/default/js/scripts.js'}
				    <li><a href="{$U_SEARCH_RULES}" onclick="popuphelp(this.href); return false;" title="{'Search rules'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					<span class="pwg-icon pwg-icon-help"></span><span class="pwg-button-text">(?)</span>
				    </a></li>
				{/if}
				{if isset($U_SLIDESHOW)}
				    <li id="cmdSlideshow">{strip}<a href="{$U_SLIDESHOW}" title="{'slideshow'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					<span class="pwg-icon pwg-icon-slideshow"></span><span class="pwg-button-text">{'slideshow'|translate}</span>
				    </a>{/strip}</li>
				{/if}
				{if isset($U_MODE_FLAT)}
				    <li>{strip}<a href="{$U_MODE_FLAT}" title="{'display all photos in all sub-albums'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					<span class="pwg-icon pwg-icon-category-view-flat"></span><span class="pwg-button-text">{'display all photos in all sub-albums'|translate}</span>
				    </a>{/strip}</li>
				{/if}
				{if isset($U_MODE_NORMAL)}
				    <li>{strip}<a href="{$U_MODE_NORMAL}" title="{'return to normal view mode'|translate}" class="pwg-state-default pwg-button">
					<span class="pwg-icon pwg-icon-category-view-normal"></span><span class="pwg-button-text">{'return to normal view mode'|translate}</span>
				    </a>{/strip}</li>
				{/if}
				{if isset($U_MODE_POSTED)}
				    <li>{strip}<a href="{$U_MODE_POSTED}" title="{'display a calendar by posted date'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					<span class="pwg-icon pwg-icon-calendar"></span><span class="pwg-button-text">{'Calendar'|translate}</span>
				    </a>{/strip}</li>
				{/if}
				{if isset($U_MODE_CREATED)}
				    <li>{strip}<a href="{$U_MODE_CREATED}" title="{'display a calendar by creation date'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
					<span class="pwg-icon pwg-icon-camera-calendar"></span><span class="pwg-button-text">{'Calendar'|translate}</span>
				    </a>{/strip}</li>
				{/if}
				{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
			    </ul>

			    {if isset($chronology_views)}
				<div class="calendarViews">{'View'|translate}:
				    <a id="calendarViewSwitchLink" href="#">
					{foreach from=$chronology_views item=view}{if $view.SELECTED}{$view.CONTENT}{/if}{/foreach}
				    </a>
				    <div id="calendarViewSwitchBox" class="switchBox">
					{foreach from=$chronology_views item=view name=loop}{if !$smarty.foreach.loop.first}<br>{/if}
					    <span{if !$view.SELECTED} class="visually-hidden"{/if}>&#x2714; </span><a href="{$view.VALUE}">{$view.CONTENT}</a>
					{/foreach}
				    </div>
				</div>
			    {/if}

			    {if isset($chronology.TITLE)}
				<h2 class="calendarTitle">{$chronology.TITLE}</h2>
			    {/if}

			</div>
		    {/block}
		</aside>
	    {/block}
	</main>

	<footer>
	    {block name="footer"}
		<div class="copyright">
	    	    {'Powered by'|translate} <a href="{$PHPWG_URL}">Phyxo</a>
	    	    {$VERSION}
		</div>
		<div>
	    	    {if isset($debug.TIME)}
	    		{'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME})
	    	    {/if}
	    	    {if isset($CONTACT_MAIL)}
	    		- <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|@escape:url}">{'Contact webmaster'|translate}</a>
	    	    {/if}
		</div>
	    	{if isset($footer_elements)}
	    	    {foreach $footer_elements as $element}
	    		{$element}
	    	    {/foreach}
	    	{/if}
	    {/block}
	</footer>

	{if isset($debug.QUERIES_LIST)}
	    <div id="debug">
	    	{foreach $debug.QUERIES_LIST as $query}
	    	    {$query.sql}
	    	{/foreach}
	    </div>
	{/if}
    </body>
</html>
