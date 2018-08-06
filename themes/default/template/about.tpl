{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'About'|translate}</h2>
{/block}

{block name="main-content"}
    {$ABOUT_MESSAGE}
    {if isset($THEME_ABOUT) }
	{$THEME_ABOUT}
    {/if}
{/block}
