{extends file="index.tpl"}

{block name="menubar"}{/block}
{block name="outer-context"}{/block}

{block name="breadcrumb"}
    {if isset($U_SLIDESHOW_STOP) }
	[ <a href="{$U_SLIDESHOW_STOP}">{'stop the slideshow'|translate}</a> ]
    {/if}
    <h2 class="showtitle">{$current.TITLE}</h2>
{/block}

{block name="content"}
    <div id="imageToolBar">
	<div class="imageNumber">{$PHOTO}</div>
	{include file="_picture_nav_buttons.tpl"}
    </div>

    {$ELEMENT_CONTENT}
    {if isset($COMMENT_IMG)}
	<p class="showlegend">{$COMMENT_IMG}</p>
    {/if}
{/block}
