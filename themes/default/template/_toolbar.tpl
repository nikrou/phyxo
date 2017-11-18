<div class="toolbar">
    <ul>
	{if isset($favorite)}
	    <li>
		<a href="{$favorite.U_FAVORITE}" title="{'delete all photos from your favorites'|translate}">
		    <i class="fa fa-heart-o"></i><span class="visually-hidden">{'delete all photos from your favorites'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_CADDIE)}
	    <li>
		<a href="{$U_CADDIE}" title="{'Add to caddie'|translate}">
		    <i class="fa fa-cart-plus"></i><span class="visually-hidden">{'Caddie'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_EDIT)}
	    <li>
		<a href="{$U_EDIT}" title="{'Edit album'|translate}">
		    <i class="fa fa-edit"></i><span class="visually-hidden">{'Edit'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_SEARCH_RULES)}
	    <li>
		<a href="{$U_SEARCH_RULES}" title="{'Search rules'|translate}">(?)</a>
	    </li>
	{/if}
	{if isset($U_SLIDESHOW)}
	    <li>
		<a href="{$U_SLIDESHOW}" title="{'slideshow'|translate}">
		    <i class="fa fa-play"></i><span class="visually-hidden">{'slideshow'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_MODE_FLAT)}
	    <li><a href="{$U_MODE_FLAT}" title="{'display all photos in all sub-albums'|translate}">{'display all photos in all sub-albums'|translate}</a></li>
	{/if}
	{if isset($U_MODE_NORMAL)}
	    <li><a href="{$U_MODE_NORMAL}" title="{'return to normal view mode'|translate}">{'return to normal view mode'|translate}</a></li>
	{/if}
	{if isset($U_MODE_POSTED)}
	    <li>
		<a href="{$U_MODE_POSTED}" title="{'display a calendar by posted date'|translate}">
		    <i class="fa fa-calendar-o"></i><span class="visually-hidden">{'Calendar'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_MODE_CREATED)}
	    <li>
		<a href="{$U_MODE_CREATED}" title="{'display a calendar by creation date'|translate}">
		    <i class="fa fa-calendar"></i><span class="visually-hidden">{'Calendar'|translate}</span>
		</a>
	    </li>
	{/if}
	{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
    </ul>

    {if isset($chronology_views)}
	<div class="infos calendar">
	    <h3>{'Calendar'|translate}</h3>
	    <ul>
		{foreach $chronology_views as $view}
		    <li>
			<i class="fa fa-check{if !$view.SELECTED} visually-hidden{/if}"></i>
			<a href="{$view.VALUE}">{$view.CONTENT}</a>
		    </li>
		{/foreach}
	    </ul>
	</div>
    {/if}
</div>
