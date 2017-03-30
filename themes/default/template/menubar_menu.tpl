<h3>{'Menu'|translate}</h3>
<div>
    {if isset($block->data.qsearch) and  $block->data.qsearch==true}
	<form action="qsearch.php" method="get" id="quicksearch">
	    <p>
		<input type="text" name="q" id="qsearchInput" value="{if !empty($QUERY_SEARCH)}{$QUERY_SEARCH}{/if}">
	    </p>
	</form>
		{/if}
		<ul>
		    {foreach $block->data as $link}
			{if is_array($link)}
			    <li><a href="{$link.URL}" title="{$link.TITLE}" {if isset($link.REL)} {$link.REL}{/if}>{$link.NAME}</a>{if isset($link.COUNTER)} ({$link.COUNTER}){/if}</li>
			{/if}
		    {/foreach}
    </ul>
</div>
