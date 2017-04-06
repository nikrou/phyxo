{block name="menubar-specials"}
    <div class="block">
	<h3>{'Specials'|translate}</h3>
	<ul>
	    {foreach $block->data as $link}
		<li><a href="{$link.URL}" title="{$link.TITLE}"{if isset($link.REL)} {$link.REL}{/if}>{$link.NAME}</a></li>
	    {/foreach}
	</ul>
    </div>
{/block}
