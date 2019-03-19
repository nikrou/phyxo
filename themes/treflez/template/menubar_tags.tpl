<li class="nav-item dropdown">
    <button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown">{'Related tags'|translate}</button>
    <div class="dropdown-menu dropdown-menu-right" role="menu">
	{foreach $block->data as $tag}
            <a class="dropdown-item tagLevel{$tag.level}" href=
	       {if isset($tag.U_ADD)}
               "{$tag.U_ADD}" title="{$tag.counter|translate_dec:'%d photo is also linked to current tags':'%d photos are also linked to current tags'}">+
	       {else}
		"{$tag.URL}" title="{'display photos linked to this tag'|translate}">
	       {/if}
               {$tag.name}
            </a>
	{/foreach}
    </div>
</li>
