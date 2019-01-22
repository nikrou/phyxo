{extends file="__layout.tpl"}

{block name="content"}
    {if !empty($category_thumbnails)} {* display sub-albums *}
	{include file="_albums.tpl"}
    {/if}

    {if !empty($thumbnails)}
	{include file="_thumbnails.tpl"}
    {/if}
{/block}
