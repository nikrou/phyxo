<li class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">{$block->get_title()}</a>
    <div class="dropdown-menu dropdown-menu-right" role="menu">
	{foreach $block->data as $data}
	    <a class="dropdown-item" href="{$data.URL}">{$data.LABEL}</a>
	{/foreach}
    </div>
</li>
