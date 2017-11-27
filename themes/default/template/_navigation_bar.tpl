<ul class="pagination">
    {if isset($navbar.URL_FIRST)}
	<li class="page-item">
	    <a class="page-link" href="{$navbar.URL_FIRST}" aria-label="{'First'|translate}" rel="first">
		<i class="fa fa-fast-backward"></i>
		<span class="visually-hidden">{'First'|translate}</span>
	    </a>
	</li>
	<li class="page-item">
	    <a class="page-link" href="{$navbar.URL_PREV}" rel="prev">
		<i class="fa fa-backward"></i>
		<span class="visually-hidden">{'Previous'|translate}</span>
	    </a>
	</li>
    {else}
	<li class="page-item disabled">
	    <span class="page-link">
		<i class="fa fa-fast-backward"></i>
		<span class="visually-hidden">{'First'|translate}</span>
	    </span>
	</li>
	<li class="page-item disabled">
	    <span class="page-link">
		<i class="fa fa-backward"></i>
		<span class="visually-hidden">{'Previous'|translate}</span>
	    </span>
	</li>
    {/if}

    {assign var='prev_page' value=0}
    {foreach $navbar.pages as $page => $url}
	{if $page > $prev_page+1}<li class="page-item"><span class="page-link">...</span></li>{/if}
	{if $page == $navbar.CURRENT_PAGE}
	    <li class="page-item active"><span class="page-link">{$page}</span></li>
	{else}
	    <li class="page-item"><a class="page-link" href="{$url}">{$page}</a></li>
	{/if}
	{assign var='prev_page' value=$page}
    {/foreach}

    {if isset($navbar.URL_NEXT)}
	<li class="page-item">
	    <a class="page-link" href="{$navbar.URL_NEXT}" rel="next">
		<i class="fa fa-forward"></i>
		<span class="visually-hidden">{'Next'|translate}</span>
	    </a>
	</li>
	<li class="page-item">
	    <a class="page-link" href="{$navbar.URL_LAST}" rel="last">
		<i class="fa fa-fast-forward"></i>
		<span class="visually-hidden">{'Last'|translate}</span>
	    </a>
	</li>
    {else}
	<li class="page-item disabled">
	    <span class="page-link">
		<i class="fa fa-forward"></i>
		<span class="visually-hidden">{'Next'|translate}</span>
	    </span>
	</li>
	<li class="page-item disabled">
	    <span class="page-link">
		<i class="fa fa-fast-forward"></i>
		<span class="visually-hidden">{'Last'|translate}</span>
	    </span>
	</li>
    {/if}
</ul>
