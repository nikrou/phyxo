{extends file="__layout.tpl"}

{block name="content"}
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
{/block}
