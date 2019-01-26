<li id="categoriesDropdownMenu" class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">{'Albums'|translate}</a>
    <div class="dropdown-menu dropdown-menu-right" role="menu">
	{assign var='ref_level' value=0}
	{foreach $block->data.MENU_CATEGORIES as $cat}
            <a class="dropdown-item{if $cat.SELECTED} active{/if}" data-level="{($cat.LEVEL -1)}" href="{$cat.URL}">
		{$cat.NAME}
		{if $cat.count_images > 0}
		    <span class="badge badge-secondary ml-2" title="{$cat.TITLE}">{$cat.count_images}</span>
		{/if}
		{if !empty($cat.icon_ts)}
		    <i class="fa fa-exclamation"></i>
		{/if}
            </a>
	{/foreach}
        <div class="dropdown-divider"></div>
        <div class="dropdown-header">{$block->data.NB_PICTURE|translate_dec:'%d photo':'%d photos'}</div>
    </div>
</li>
