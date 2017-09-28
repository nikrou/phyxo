<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	{block name="head"}
	    {block name="head_title"}
		<title>{if $PAGE_TITLE!=l10n('Home') && $PAGE_TITLE!=$GALLERY_TITLE}{$PAGE_TITLE} | {/if}{$GALLERY_TITLE}</title>
	    {/block}
	    {block name="head_meta"}
		<meta charset="UTF-8">
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
	    {/block}

	    {block name="head_theme"}
		{foreach $themes as $theme}
	    	    {if $theme.load_css}
			<link rel="stylesheet" href="themes/{$theme.id}/css/style.css">
	    	    {/if}
	    	    {if !empty($theme.local_head)}
	    		{include file=$theme.local_head load_css=$theme.load_css}
	    	    {/if}
		{/foreach}
	    {/block}
	    {block name="head_links"}
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
	    {/block}
	    {if not empty($head_elements)}
	    	{foreach $head_elements as $element}
	    	    {$element}
	    	{/foreach}
	    {/if}
	    {block name="head_html"}{/block}
	{/block}
	{include file="_local_head.tpl"}
    </head>
    <body>
	{block name="html_body"}
	    <div class="wrapper">
		{block name="header_wrapper"}
		    <header>
			{block name="header"}
			    <div class="banner">{$PAGE_BANNER}</div>
			{/block}
		    </header>
		{/block}

		{block name="main_wrapper"}
		    <main>
			{block name="main"}
			    <section role="content">
				{block name="log_wrapper"}
				    {if !empty($header_notes) or !empty($errors) or !empty($infos)}
					<section role="log">
					    {block name="log"}
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
					    {/block}
					</section>
				    {/if}
				{/block}

				<section role="content-info">
				    {block name="breacrumb_wrapper"}
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
				    {/block}
				    {block name="content-toolbar_wrapper"}
					<div class="toolbar">
					    {block name="content-toolbar"}{/block}
					</div>
				    {/block}
				</section>

				{block name="main-content_wrapper"}
				    {if !empty($PLUGIN_INDEX_CONTENT_BEFORE)}{$PLUGIN_INDEX_CONTENT_BEFORE}{/if}
				    <div class="main-content">
					{if !empty($PLUGIN_INDEX_CONTENT_BEGIN)}{$PLUGIN_INDEX_CONTENT_BEGIN}{/if}
					{block name="main-content"}{/block}
					{if !empty($PLUGIN_INDEX_CONTENT_END)}{$PLUGIN_INDEX_CONTENT_END}{/if}
				    </div>

				    {if !empty($PLUGIN_INDEX_CONTENT_AFTER)}{$PLUGIN_INDEX_CONTENT_AFTER}{/if}
				{/block}
			    </section>

			    {block name="context_wrapper"}
				<aside role="complementary" class="context">
				    {block name="context"}
					<p><i class="fa fa-close"></i></p>
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

			    {block name="navigation_wrapper"}
				<aside role="navigation">
				    {include file="_menubar.tpl"}
				</aside>
			    {/block}

			{/block}
		    </main>
		{/block}

		{block name="footer_wrapper"}
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
		{/block}

		{block name="debug"}
		    {if isset($debug.QUERIES_LIST)}
			<div id="debug">
	    		    {foreach $debug.QUERIES_LIST as $query}
	    			{$query.sql}
	    		    {/foreach}
			</div>
		    {/if}
		{/block}
	    </div>
	{/block}

	{block name="footer_scripts"}{/block}
	{include file="_local_footer.tpl"}
    </body>
</html>
