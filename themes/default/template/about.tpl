{extends file="index.tpl"}

{block name="content"}
    <div class="titrePage">
	<ul class="categoryActions">
	</ul>
	<h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'About'|translate}</h2>
    </div>

    <div>
	{$ABOUT_MESSAGE}
	{if isset($THEME_ABOUT) }
	    <ul>
		<li>{$THEME_ABOUT}</li>
	    </ul>
	{/if}
	{if not empty($about_msgs)}
	    {foreach $about_msgs as $element}
		{$element}
	    {/foreach}
	{/if}
    </div>
{/block}
