{extends file="__layout.tpl"}

{block name="main-content"}
    {if !empty($category_thumbnails)} {* display sub-albums *}
	{include file="_albums.tpl"}
    {/if}

    {include file="_thumbnails.tpl"}

    {if !empty($thumb_navbar)}
	{include file="_navigation_bar.tpl" navbar=$thumb_navbar}
    {/if}
{/block}
