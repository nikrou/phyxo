{extends file="__layout.tpl"}

{block name="category-actions"}{/block}

{block name="content"}
    <div class="titrePage">
	<h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'About'|translate}</h2>
    </div>

    <div id="piwigoAbout">
	{$ABOUT_MESSAGE}
	{if isset($THEME_ABOUT) }
	    <ul>
		<li>{$THEME_ABOUT}</li>
	    </ul>
	{/if}
	{if not empty($about_msgs)}
	    {foreach from=$about_msgs item=elt}
		{$elt}
	    {/foreach}
	{/if}
    </div>
{/block}
