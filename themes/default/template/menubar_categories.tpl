{function name="menu" level="0"}
<ul class="level-{$level}">
    {foreach $data as $entry}
	{if isset($entry['children'])}
	    <li>
		<a href="{$entry.URL}">{$entry.NAME}</a>
		{call name="menu" data=$entry['children'] level=$level+1}
	    </li>
	{else}
	    <li><a{if $entry.SELECTED} class="active"{/if} href="{$entry.URL}">{$entry['name']}</a></li>
	{/if}
    {/foreach}
</ul>
{/function}

{block name="menubar-categories"}
    <div class="menu-block">
	<h3>
	    <a href="{$block->data.U_CATEGORIES}">{'Albums'|translate}</a>
	    {if isset($U_START_FILTER)}
		<a href="{$U_START_FILTER}" title="{'display only recently posted photos'|translate}">
		    <i class="fa fa-filter"></i><span class="visually-hidden">{'display only recently posted photos'|translate}</span>
		</a>
	    {/if}
	    {if isset($U_STOP_FILTER)}
		<a href="{$U_STOP_FILTER}" title="{'return to the display of all photos'|translate}">
		    <i class="fa fa-filter"></i><span class="visually-hidden">{'return to the display of all photos'|translate}</span>
		</a>
	    {/if}
	</h3>

	{call name="menu" data=$block->data.MENU_RECURSIVE_CATEGORIES}

	<p class="totalImages">{$block->data.NB_PICTURE|translate_dec:'%d photo':'%d photos'}</p>
    </div>
{/block}
