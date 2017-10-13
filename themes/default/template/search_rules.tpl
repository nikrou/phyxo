{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'Search rules'|translate}</h2>
{/block}

{block name="main-content"}
    {if isset($INTRODUCTION)}
	<p>{$INTRODUCTION}</p>
    {/if}

    <ul>
	{if isset($search_words)}
	    {foreach from=$search_words item=v}
		<li>{$v}</li>
	    {/foreach}
	{/if}

	{if isset($SEARCH_TAGS_MODE) }
	    <li>
		<p>{if 'AND'==$SEARCH_TAGS_MODE}{'All tags'|translate}{else}{'Any tag'|translate}{/if}</p>
		<ul>
		    {foreach $search_tags as $i => $v}
			<li>{$v.name}</li>
		    {/foreach}
		</ul>
	    </li>
	{/if}

	{if isset($DATE_CREATION)}
	    <li>{$DATE_CREATION}</li>
	{/if}

	{if isset($DATE_AVAILABLE)}
	    <li>{$DATE_AVAILABLE}</li>
	{/if}

	{if isset($search_categories)}
	    <li>
		<p>{'Albums'|translate}</p>

		<ul>
		    {foreach from=$search_categories item=v}
			<li>{$v}</li>
		    {/foreach}
		</ul>
	    </li>
	{/if}
    </ul>
{/block}
