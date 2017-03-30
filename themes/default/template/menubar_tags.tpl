<h3>{'Related tags'|translate}</h3>
<div id="menuTagCloud">
    {foreach $block->data as $tag}
	<span>
	    <a class="tagLevel{$tag.level}"
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
