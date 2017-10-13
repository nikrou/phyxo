{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="main-content"}
    {include file="_albums.tpl"}

    {if !empty($cats_navbar)}
	{include file="_navigation_bar.tpl" navbar=$cats_navbar}
    {/if}
{/block}
