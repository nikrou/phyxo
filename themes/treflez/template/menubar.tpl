<ul class="navbar-nav{if !$theme_config->quicksearch_navbar} ml-auto{/if}">
    {if !empty($blocks) }
	{assign var="discover_menu_exists" value=false}
	{foreach $blocks as $id => $block}
	    {if not empty($block->template)}
		{if $id != "mbMenu" && $id != "mbSpecials"}
		    {include file=$block->template }
		{/if}
		{if $discover_menu_exists == false && ($id == "mbSpecials" or $id == "mbMenu")}
		    <li class="nav-item dropdown">
			<button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown">{'Discover'|translate}</button>
			<div class="dropdown-menu dropdown-menu-right" role="menu">
			    {if not empty($blocks.mbMenu->template)}
				{include file=$blocks.mbMenu->template}
			    {/if}
			    {if not empty($blocks.mbSpecials->template)}
				{if not empty($blocks.mbMenu->template)}
				    <div class="dropdown-divider"></div>
				{/if}
				{include file=$blocks.mbSpecials->template}
			    {/if}
			</div>
		    </li>
		    {assign var="discover_menu_exists" value=true}
		{/if}
	    {else}
		{$block->raw_content}
	    {/if}
	{/foreach}
    {/if}
</ul>
