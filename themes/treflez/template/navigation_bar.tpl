<nav class="mt-5" aria-label="Page navigation">
    <ul class="pagination justify-content-center">
	{if isset($navbar.URL_FIRST)}
            <li class="page-item"><a class="page-link" href="{$navbar.URL_FIRST}" rel="first"><i class="fa fa-fast-backward" title="{'First'|translate}"></i></a></li>
            <li class="page-item"><a class="page-link" href="{$navbar.URL_PREV}" rel="prev"><i class="fa fa-backward" title="{'Previous'|translate}"></i></a></li>
	{else}
            <li class="page-item disabled"><span class="page-link"><i class="fa fa-fast-backward" title="{'First'|translate}"></i></span></li>
            <li class="page-item disabled"><span class="page-link"><i class="fa fa-backward" title="{'Previous'|translate}"></i></span></li>
	{/if}
	{assign var='prev_page' value=0}
	{foreach $navbar.pages as $page => $url}
	    {if $page == $navbar.CURRENT_PAGE}
		<li class="page-item active"><span class="page-link">{$page}</span></li>
	    {else}
		<li class="page-item"><a class="page-link" href="{$url}">{$page}</a></li>
	    {/if}
	    {assign var='prev_page' value=$page}
	{/foreach}

	{if isset($navbar.URL_NEXT)}
            <li class="page-item"><a class="page-link" href="{$navbar.URL_NEXT}" rel="next"><i class="fa fa-forward" title="{'Next'|translate}"></i></a></li>
            <li class="page-item"><a class="page-link" href="{$navbar.URL_LAST}" rel="last"><i class="fa fa-fast-forward" title="{'Last'|translate}"></i></a></li>
	{else}
            <li class="page-item disabled"><span class="page-link"><i class="fa fa-forward" title="{'Next'|translate}"></i></span></li>
            <li class="page-item disabled"><span class="page-link"><i class="fa fa-fast-forward" title="{'Last'|translate}"></i></span></li>
	{/if}
    </ul>
</nav>
