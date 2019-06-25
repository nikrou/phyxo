{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{'Search'|translate}
	    </div>
            <ul class="navbar-nav justify-content-end">
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
            </ul>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
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
		    <p>{if 'AND'==$SEARCH_TAGS_MODE}{'All tags'|@translate}{else}{'Any tag'|@translate}{/if}</p>
		    <ul>
			{foreach from=$search_tags item=v}
			    <li>{$v}</li>
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
		    <p>{'Albums'|@translate}</p>

		    <ul>
			{foreach from=$search_categories item=v}
			    <li>{$v}</li>
			{/foreach}
		    </ul>
		</li>
	    {/if}
	</ul>
    </div>
{/block}
