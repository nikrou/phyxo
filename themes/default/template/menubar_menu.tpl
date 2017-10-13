{block name="menbar-menu"}
    <div class="block">
	{if isset($block->data.qsearch) and  $block->data.qsearch==true}
	    <form action="qsearch.php" method="get" id="quicksearch">
		<p>
		    <input type="text" placeholder="{'Quick search'|translate}" name="q" id="qsearchInput" value="{if !empty($QUERY_SEARCH)}{$QUERY_SEARCH}{/if}">
		</p>
	    </form>
	{/if}
	<ul>
	    {foreach $block->data as $link}
		{if is_array($link)}
		    <li>
			<a href="{$link.URL}" title="{$link.TITLE}" {if isset($link.REL)} {$link.REL}{/if}>
			    {$link.NAME}
			    {if isset($link.COUNTER)}
				<span class="count">[{$link.COUNTER}]</span>
			    {/if}
			</a>
		    </li>
		{/if}
	    {/foreach}
	</ul>
    </div>
{/block}
