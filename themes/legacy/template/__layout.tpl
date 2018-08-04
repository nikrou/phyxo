{include file="header.tpl"}

{combine_script id="core.switchbox" load="async" require="jquery" path="themes/legacy/js/switchbox.js"}

{block name="menubar"}
    {include file="menubar.tpl"}
{/block}

{if isset($errors) or isset($infos)}
    <div class="content messages{if isset($MENUBAR)} contentWithMenu{/if}">
	{include file="infos_errors.tpl"}
    </div>
{/if}

{if !empty($PLUGIN_INDEX_CONTENT_BEFORE)}{$PLUGIN_INDEX_CONTENT_BEFORE}{/if}
<div id="content" class="content{if isset($MENUBAR)} contentWithMenu{/if}">
    {block name="category-actions"}
	<div class="titrePage{if isset($chronology.TITLE)} calendarTitleBar{/if}">
	    <ul class="categoryActions">
		{if !empty($image_orders)}
		    <li>{strip}<a id="sortOrderLink" title="{'Sort order'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
			<span class="pwg-icon pwg-icon-sort"></span><span class="pwg-button-text">{'Sort order'|translate}</span>
		    </a>
		    <div id="sortOrderBox" class="switchBox">
			<div class="switchBoxTitle">{'Sort order'|translate}</div>
			{foreach from=$image_orders item=image_order name=loop}{if !$smarty.foreach.loop.first}<br>{/if}
			    {if $image_order.SELECTED}
				<span>&#x2714; </span>{$image_order.DISPLAY}
			    {else}
				<span style="visibility:hidden">&#x2714; </span><a href="{$image_order.URL}" rel="nofollow">{$image_order.DISPLAY}</a>
			    {/if}
			{/foreach}
		    </div>
		    {footer_script}(window.SwitchBox=window.SwitchBox||[]).push("#sortOrderLink", "#sortOrderBox");{/footer_script}
		{/strip}</li>
		{/if}
		{if !empty($image_derivatives)}
		    <li>{strip}<a id="derivativeSwitchLink" title="{'Photo sizes'|translate}" class="pwg-state-default pwg-button" rel="nofollow">
			<span class="pwg-icon pwg-icon-sizes"></span><span class="pwg-button-text">{'Photo sizes'|translate}</span>
		    </a>
		    <div id="derivativeSwitchBox" class="switchBox">
			<div class="switchBoxTitle">{'Photo sizes'|translate}</div>
			{foreach from=$image_derivatives item=image_derivative name=loop}{if !$smarty.foreach.loop.first}<br>{/if}
			    {if $image_derivative.SELECTED}
				<span>&#x2714; </span>{$image_derivative.DISPLAY}
			    {else}
				<span style="visibility:hidden">&#x2714; </span><a href="{$image_derivative.URL}" rel="nofollow">{$image_derivative.DISPLAY}</a>
			    {/if}
			{/foreach}
		    </div>
		    {footer_script}(window.SwitchBox=window.SwitchBox||[]).push("#derivativeSwitchLink", "#derivativeSwitchBox");{/footer_script}
		{/strip}</li>
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
		{if !empty($PLUGIN_INDEX_BUTTONS)}{foreach from=$PLUGIN_INDEX_BUTTONS item=button}<li>{$button}</li>{/foreach}{/if}
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
	    </ul>

	    <h2>{$TITLE}</h2>

	    {if isset($chronology_views)}
		<div class="calendarViews">{'View'|translate}:
		    <a id="calendarViewSwitchLink" href="#">
			{foreach from=$chronology_views item=view}{if $view.SELECTED}{$view.CONTENT}{/if}{/foreach}
		    </a>
		    <div id="calendarViewSwitchBox" class="switchBox">
			{foreach from=$chronology_views item=view name=loop}{if !$smarty.foreach.loop.first}<br>{/if}
			    <span{if !$view.SELECTED} style="visibility:hidden"{/if}>&#x2714; </span><a href="{$view.VALUE}">{$view.CONTENT}</a>
			{/foreach}
		    </div>
		    {footer_script}(window.SwitchBox=window.SwitchBox||[]).push("#calendarViewSwitchLink", "#calendarViewSwitchBox");{/footer_script}
		</div>
	    {/if}

	    {if isset($chronology.TITLE)}
		<h2 class="calendarTitle">{$chronology.TITLE}</h2>
	    {/if}
	</div>{* <!-- titrePage --> *}
    {/block}

    {if !empty($PLUGIN_INDEX_CONTENT_BEGIN)}{$PLUGIN_INDEX_CONTENT_BEGIN}{/if}


    {if !empty($CONTENT_DESCRIPTION)}
	<div class="additional_info">
	    {$CONTENT_DESCRIPTION}
	</div>
    {/if}

    {block name="content"}{/block}

    {if !empty($cats_navbar)}
	{include file='navigation_bar.tpl' navbar=$cats_navbar}
    {/if}

    {if !empty($thumb_navbar)}
	{include file='navigation_bar.tpl' navbar=$thumb_navbar}
    {/if}

    {if !empty($PLUGIN_INDEX_CONTENT_END)}{$PLUGIN_INDEX_CONTENT_END}{/if}
</div>{* <!-- content --> *}
{if !empty($PLUGIN_INDEX_CONTENT_AFTER)}{$PLUGIN_INDEX_CONTENT_AFTER}{/if}

{include file="footer.tpl"}
