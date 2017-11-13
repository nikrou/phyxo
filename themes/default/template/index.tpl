{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="main-content"}
    {if !empty($no_search_results)}
	<p class="search_results">{'No results for'|translate} :
	    <em><strong>
		{foreach $no_search_results as $res}
		    {if !$res@first} &mdash; {/if}
		    {$res}
		{/foreach}
	    </strong></em>
	</p>
    {/if}

    {if !empty($category_search_results)}
	<p class="search_results">{'Album results for'|translate} <strong>{$QUERY_SEARCH}</strong> :
	    <em><strong>
		{foreach $category_search_results as $res}
		    {if !$res@first} &mdash; {/if}
		    {$res}
		{/foreach}
	    </strong></em>
	</p>
    {/if}

    {if !empty($tag_search_results)}
	<p class="search_results">{'Tag results for'|translate} <strong>{$QUERY_SEARCH}</strong> :
	    <em><strong>
		{foreach $tag_search_results as $tag}
		    {if !$tag@first} &mdash; {/if} <a href="{$tag.URL}">{$tag.name}</a>
		{/foreach}
	    </strong></em>
	</p>
    {/if}

    {if !empty($cats_navbar)}
	{include file="_navigation_bar.tpl" navbar=$cats_navbar}
    {/if}
    {if !empty($THUMBNAILS)}
	<div class="thumbnails">
	    {$THUMBNAILS}
	</div>
    {/if}
    {if !empty($thumb_navbar)}
	{include file="_navigation_bar.tpl" navbar=$thumb_navbar}
    {/if}
{/block}

{block name="toolbar"}
    {include file="_toolbar.tpl"}
{/block}
