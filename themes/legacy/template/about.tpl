{extends file="__layout.tpl"}

{block name="category-actions"}{/block}

{block name="content"}
    <div class="titrePage">
	<h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'About'|translate}</h2>
    </div>

    <div id="piwigoAbout">
	{$ABOUT_MESSAGE}
	{if isset($THEME_ABOUT) }
	    {$THEME_ABOUT}
	{/if}
    </div>
{/block}
