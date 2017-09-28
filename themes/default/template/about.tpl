{extends file="__layout.tpl"}

{block name="wrapper_context"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'About'|translate}</h2>
{/block}

{block name="main-content"}
    {$ABOUT_MESSAGE}
    {if isset($THEME_ABOUT) }
	<ul>
	    <li>{$THEME_ABOUT}</li>
	</ul>
    {/if}
    {if !empty($about_msgs)}
	{foreach $about_msgs as $element}
	    {$element}
	{/foreach}
    {/if}
{/block}
