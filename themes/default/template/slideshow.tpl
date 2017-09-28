{extends file="__layout.tpl"}

{block name="menubar"}{/block}
{block name="context_wrapper"}{/block}

{block name="breadcrumb"}
    {if isset($U_SLIDESHOW_STOP) }
	[ <a href="{$U_SLIDESHOW_STOP}">{'stop the slideshow'|translate}</a> ]
    {/if}
    <h2 class="showtitle">{$current.TITLE}</h2>
{/block}

{block name="main-content"}
    <div id="imageToolBar">
	<div class="imageNumber">{$PHOTO}</div>
	{include file="_picture_nav_buttons.tpl"}
    </div>

    <img src="{$current.selected_derivative->get_url()}" alt="{$ALT_IMG}" title="{if isset($COMMENT_IMG)}{$COMMENT_IMG|strip_tags:false|replace:'"':' '}{else}{$current.TITLE_ESC} - {$ALT_IMG}{/if}">
    {if isset($COMMENT_IMG)}
	<p class="showlegend">{$COMMENT_IMG}</p>
    {/if}
{/block}
