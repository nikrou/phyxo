<li class="nav-item dropdown">
    <button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown">{'Related tags'|translate}</button>
    <div class="dropdown-menu dropdown-menu-right" role="menu">
	{foreach $block->data as $tag}
            <a class="dropdown-item tagLevel{$tag.level}" href=
	       {if isset($tag.U_ADD)}
               "{$tag.U_ADD}" title="{'number_of_photos_linked_to_current_tags'|translate:['count' => $tag.counter]}">+
	       {else}
		"{$tag.URL}" title="{'display photos linked to this tag'|translate}">
	       {/if}
               {$tag.name}
            </a>
	{/foreach}
    </div>
</li>
