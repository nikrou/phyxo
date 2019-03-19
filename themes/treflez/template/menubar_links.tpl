<li class="nav-item dropdown" id="linksDropdown">
    <button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown">{'Links'|translate}</button>
    <div class="dropdown-menu dropdown-menu-right" role="menu">
	{foreach $block->data as $link}
            <a href="{$link.URL}" class="dropdown-item{if isset($link.new_window)} external{/if}">{$link.LABEL}</a>
	{/foreach}
    </div>
</li>
