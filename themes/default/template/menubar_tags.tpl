{block name="menubar-tags"}
    <div class="block">
	<h3>{'Related tags'|translate}</h3>
	{foreach $block->data as $tag}
	    <span>
		<a class="tag"
		   href={if isset($tag.U_ADD)}
		   "{$tag.U_ADD}" title="{$tag.counter|translate_dec:'%d photo is also linked to current tags':'%d photos are also linked to current tags'}" rel="nofollow">+
		   {else}
		    "{$tag.URL}" title="{'display photos linked to this tag'|translate}">
		   {/if}
	       {$tag.name}
		</a>
	    </span>
	{/foreach}
    </div>
{/block}
