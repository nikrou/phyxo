<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
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
	    	    <meta name="keywords" content="{foreach $related_tags as $tag}{if !$tag@first}, {/if}{$tag.name}{/foreach}">
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
	<div class="wrapper">
	    <header>
		{block name="header"}
		    <div class="banner">{$PAGE_BANNER}</div>
		{/block}
	    </header>

	    <main>
		{block name="main"}
		    <section role="content">
			{if !empty($header_notes) or !empty($errors) or !empty($infos)}
			    <section role="log">
				{if !empty($header_notes)}
				    <div class="notes header">
					{foreach $header_notes as $element}
					    <p>{$element}</p>
					{/foreach}
				    </div>
				{/if}
				{if !empty($errors) or !empty($infos)}
		    		    {include file="_infos_errors.tpl"}
				{/if}
			    </section>
			{/if}
			<nav class="breadcrumb">
			    {block name="breadcrumb"}
				{if !empty($TITLE)}
				    <h2>{$TITLE}</h2>
				{/if}
				{if isset($chronology.TITLE)}
				    <h2 class="calendarTitle">{$chronology.TITLE}</h2>
				{/if}
			    {/block}
			</nav>
			{if !empty($PLUGIN_INDEX_CONTENT_BEFORE)}{$PLUGIN_INDEX_CONTENT_BEFORE}{/if}
			<div class="main-content">
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
					    {foreach $category_search_results as $res}
						{if !$res@first} &mdash; {/if}
						{$res}
					    {/foreach}
					</strong></em>
				    </p>
				{/if}

				{if !empty($tag_search_results)}
				    <p class="search_results">{'Tag results for'|translate} <strong>{$QUERY_SEARCH}</strong> :
					<em><strong>
					    {foreach $tag_search_results as $tag}
						{if !$tag@first} &mdash; {/if} <a href="{$tag.URL}">{$tag.name}</a>
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
				    {include file="_navigation_bar.tpl" navbar=$cats_navbar}
				{/if}
				{if !empty($THUMBNAILS)}
				    <div class="thumbnails">
					{$THUMBNAILS}
				    </div>
				{/if}
				{if !empty($thumb_navbar)}
				    {include file="_navigation_bar.tpl" navbar=$thumb_navbar}
				{/if}
			    {/block}

			    {if !empty($PLUGIN_INDEX_CONTENT_END)}{$PLUGIN_INDEX_CONTENT_END}{/if}
			</div>

			<div class="toolbar">
			    {block name="content-toolbar"}
				<ul>
				    {if isset($favorite)}
					<li>
					    <a href="{$favorite.U_FAVORITE}" title="{'delete all photos from your favorites'|translate}">
						<i class="fa fa-heart-o"></i><span class="visually-hidden">{'delete all photos from your favorites'|translate}</span>
					    </a>
					</li>
				    {/if}
				    {if isset($U_CADDIE)}
					<li>
					    <a href="{$U_CADDIE}" title="{'Add to caddie'|translate}">
						<i class="fa fa-cart-plus"></i><span class="visually-hidden>"{'Caddie'|translate}</span>
					    </a>
					</li>
				    {/if}
				    {if isset($U_EDIT)}
					<li>
					    <a href="{$U_EDIT}" title="{'Edit album'|translate}">
						<i class="fa fa-edit"></i><span class="visually-hidden">{'Edit'|translate}</span>
					    </a>
					</li>
				    {/if}
				    {if isset($U_SEARCH_RULES)}
					<li>
					    <a href="{$U_SEARCH_RULES}" title="{'Search rules'|translate}">(?)</a>
					</li>
				    {/if}
				    {if isset($U_SLIDESHOW)}
					<li>
					    <a href="{$U_SLIDESHOW}" title="{'slideshow'|translate}">
						<i class="fa fa-play"></i><span class="visually-hidden">{'slideshow'|translate}</span>
					    </a>
					</li>
				    {/if}
				    {if isset($U_MODE_FLAT)}
					<li><a href="{$U_MODE_FLAT}" title="{'display all photos in all sub-albums'|translate}">{'display all photos in all sub-albums'|translate}</a></li>
				    {/if}
				    {if isset($U_MODE_NORMAL)}
					<li><a href="{$U_MODE_NORMAL}" title="{'return to normal view mode'|translate}">{'return to normal view mode'|translate}</a></li>
				    {/if}
				    {if isset($U_MODE_POSTED)}
					<li>
					    <a href="{$U_MODE_POSTED}" title="{'display a calendar by posted date'|translate}">
						<i class="fa fa-calendar-o"></i><span class="visually-hidden">{'Calendar'|translate}</span>
					    </a>
					</li>
				    {/if}
				    {if isset($U_MODE_CREATED)}
					<li>
					    <a href="{$U_MODE_CREATED}" title="{'display a calendar by creation date'|translate}">
						<i class="fa fa-calendar"></i><span class="visually-hidden">{'Calendar'|translate}</span>
					    </a>
					</li>
				    {/if}
				    {if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
				</ul>

				{if isset($chronology_views)}
				    <div class="infos calendar">
					<h3>{'Calendar'|translate}</h3>
					<ul>
					    {foreach $chronology_views as $view}
						<li>
						    <i class="fa fa-check{if !$view.SELECTED} visually-hidden{/if}"></i>
						    <a href="{$view.VALUE}">{$view.CONTENT}</a>
						</li>
					    {/foreach}
					</ul>
				    </div>
				{/if}
			    {/block}
			</div>
		    </section>

		    {block name="menubar"}
			<aside role="navigation">
			    {include file="_menubar.tpl"}
			</aside>
		    {/block}

		    {block name="context-wrapper"}
			<aside role="complementary">
			    <p><i class="fa fa-times"></i></p>
			    {block name="context"}
				{if !empty($image_orders)}
				    <div class="infos sort-order">
					<h3>{'Sort order'|translate}</h3>
					<ul>
					    {foreach $image_orders as $image_order}
						<li>
						    <i class="fa fa-check{if !$image_order.SELECTED} visually-hidden{/if}"></i>
						    <a href="{$image_order.URL}">{$image_order.DISPLAY}</a>
						</li>
					    {/foreach}
					</ul>
				    </div>
				{/if}

				{if !empty($image_derivatives)}
				    <div class="infos photo-sizes">
					<h3>{'Photo sizes'|translate}</h3>
					<ul>
					    {foreach $image_derivatives as $image_derivative}
						<li>
						    <i class="fa fa-check{if !$image_derivative.SELECTED} visually-hidden{/if}"></i>
						    <a href="{$image_derivative.URL}">{$image_derivative.DISPLAY}</a>
						</li>
					    {/foreach}
					</ul>
				    </div>
				{/if}
			    {/block}
			</aside>
		    {/block}
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
	    		    - <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Contact webmaster'|translate}</a>
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
	</div>
	{get_combined_scripts load="footer"}
    </body>
</html>
