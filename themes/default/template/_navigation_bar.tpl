<div class="navigation-bar">
    {if isset($navbar.URL_FIRST)}
	<a href="{$navbar.URL_FIRST}" rel="first">{'First'|translate}</a> |
	<a href="{$navbar.URL_PREV}" rel="prev">{'Previous'|translate}</a> |
    {else}
	<span class="disabled">{'First'|translate}</span> |
	<span class="disabled">{'Previous'|translate}</span> |
    {/if}

    {assign var='prev_page' value=0}
    {foreach $navbar.pages as $page => $url}
	{if $page > $prev_page+1}...{/if}
	{if $page == $navbar.CURRENT_PAGE}
	    <span class="page selected">{$page}</span>
	{else}
	    <a href="{$url}">{$page}</a>
	{/if}
	{assign var='prev_page' value=$page}
    {/foreach}

    {if isset($navbar.URL_NEXT)}
	| <a href="{$navbar.URL_NEXT}" rel="next">{'Next'|translate}</a>
	| <a href="{$navbar.URL_LAST}" rel="last">{'Last'|translate}</a>
    {else}
	| <span class="disabled">{'Next'|translate}</span>
	| <span class="disabled">{'Last'|translate}</span>
    {/if}
</div>
