<nav class="navbar navbar-expand-lg navbar-contextual {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg}{if $theme_config->page_header == 'fancy' && $theme_config->page_header_both_navs} navbar-transparent navbar-sm{/if} sticky-top mb-2">
    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
        <div class="navbar-brand mr-auto">
	    {if is_array($TITLE)}
		<a href="{$U_HOME}" title="{'Home'|translate}"><i class="fa fa-home" aria-hidden="true"></i></a>
		{foreach $TITLE as $breadcrum_element}
		    {$LEVEL_SEPARATOR}
		    {if $breadcrum_element.url}
			<a href="{$breadcrum_element.url}">{$breadcrum_element.label}</a>
		    {else}
			<span>{$breadcrum_element.label}</span>
		    {/if}
		{/foreach}
	    {else}
		{if isset($chronology.TITLE)}
                    <a href="{$U_HOME}" title="{'Home'|translate}"><i class="fa fa-home" aria-hidden="true"></i></a>{$LEVEL_SEPARATOR}{$chronology.TITLE}
		{else}
                    <div class="nav-breadcrumb d-inline-flex">{$TITLE}</div>
		{/if}
	    {/if}
        </div>
        <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#secondary-navbar" aria-controls="secondary-navbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="fa fa-bars"></span>
        </button>
        <div class="navbar-collapse collapse justify-content-end" id="secondary-navbar">
            <ul class="navbar-nav">
		{if !empty($image_orders)}
                    <li class="nav-item dropdown">
			<button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown" title="{'Sort order'|translate}">
                            <i class="fa fa-sort" aria-hidden="true"></i>
			    <span class="d-lg-none ml-2">{'Sort order'|translate}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" role="menu">
			    {foreach from=$image_orders item=image_order name=loop}
				<a class="dropdown-item{if $image_order.SELECTED} active{/if}" href="{$image_order.URL}" rel="nofollow">{$image_order.DISPLAY}</a>
			    {/foreach}
                        </div>
                    </li>
		{/if}
		{if !empty($image_derivatives)}
                    <li class="nav-item dropdown">
                        <button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown" title="{'Photo sizes'|translate}">
                            <i class="fa fa-photo" aria-hidden="true"></i>
			    <span class="d-lg-none ml-2">{'Photo sizes'|translate}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" role="menu">
			    {foreach from=$image_derivatives item=image_derivative name=loop}
				<a class="dropdown-item{if $image_derivative.SELECTED} active{/if}" href="{$image_derivative.URL}" rel="nofollow">{$image_derivative.DISPLAY}</a>
			    {/foreach}
                        </div>
                    </li>
		{/if}
		{if isset($favorite)}
                    <li class="nav-item">
                        <a class="nav-link" href="{$favorite.U_FAVORITE}" title="{'Delete all photos from your favorites'|translate}" rel="nofollow">
                            <i class="fa fa-heartbeat fa-fw" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Delete all photos from your favorites'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_EDIT)}
                    <li class="nav-item">
                        <a class="nav-link" href="{$U_EDIT}" title="{'Edit album'|translate}">
                            <i class="fa fa-pencil" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Edit album'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_CADDIE)}
                    <li class="nav-item">
                        <a class="nav-link" href="{$U_CADDIE}" title="{'Add to caddie'|translate}">
                            <i class="fa fa-shopping-basket fa-fw" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Add to caddie'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_SEARCH_RULES)}
		    {combine_script id='core.scripts' load='async' path='themes/default/js/scripts.js'}
                    <li class="nav-item">
                        <a class="nav-link" href="{$U_SEARCH_RULES}" onclick="bd_popup(this.href); return false;" title="{'Search rules'|translate}" rel="nofollow">
                            <i class="fa fa-search fa-fw" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Search rules'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_SLIDESHOW)}
                    <li class="nav-item">
                        <a class="nav-link" href="{if $theme_config->photoswipe}javascript:;{else}{$U_SLIDESHOW}{/if}" id="startSlideshow" title="{'slideshow'|translate}" rel="nofollow">
                            <i class="fa fa-play fa-fw" aria-hidden="true"></i><span class="d-lg-none ml-2 text-capitalize">{'slideshow'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_MODE_FLAT)}
                    <li class="nav-item">
                        <a class="nav-link" href="{$U_MODE_FLAT}" title="{'display all photos in all sub-albums'|translate}">
                            <i class="fa fa-th-large fa-fw" aria-hidden="true"></i>
			    <span class="d-lg-none ml-2">{'display all photos in all sub-albums'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_MODE_NORMAL)}
                    <li class="nav-item">
                        <a class="nav-link" href="{$U_MODE_NORMAL}" title="{'return to normal view mode'|translate}">
                            <i class="fa fa-sitemap" aria-hidden="true"></i>
			    <span class="d-lg-none ml-2">{'return to normal view mode'|translate}</span>
                        </a>
                    </li>
		{/if}
		{if isset($U_MODE_POSTED) || isset($U_MODE_CREATED)}
                    <li class="nav-item dropdown">
                        <button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown" title="{'Calendar'|translate}">
                            <i class="fa fa-calendar" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Calendar'|translate}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
			    {if isset($U_MODE_POSTED)}
				<a class="dropdown-item" href="{$U_MODE_POSTED}" title="{'display a calendar by posted date'|translate}" rel="nofollow">
                                    <i class="fa fa-share" aria-hidden="true"></i> {'display a calendar by posted date'|translate}
				</a>
			    {/if}
			    {if isset($U_MODE_CREATED)}
				<a class="dropdown-item" href="{$U_MODE_CREATED}" title="{'display a calendar by creation date'|translate}" rel="nofollow">
                                    <i class="fa fa-camera" aria-hidden="true"></i> {'display a calendar by creation date'|translate}
				</a>
			    {/if}
                        </div>
                    </li>
		{/if}
		{if !empty($PLUGIN_INDEX_BUTTONS)}{foreach from=$PLUGIN_INDEX_BUTTONS item=button}<li>{$button}</li>{/foreach}{/if}
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}

		{if (!empty($category_thumbnails) || !empty($thumbnails))}
                    <li id="btn-grid" class="nav-item{if $category_view != 'list'} active{/if}">
                        <button type="button" class="btn btn-link nav-link" title="{'Grid view'|translate}">
                            <i class="fa fa-th"></i>
			    <span class="d-lg-none ml-2">{'Grid view'|translate}</span>
                        </button>
                    </li>
                    <li id="btn-list" class="nav-item{if $category_view == 'list'} active{/if}">
                        <button type="button" class="btn btn-link nav-link" title="{'List view'|translate}">
                            <i class="fa fa-th-list"></i>
			    <span class="d-lg-none ml-2">{'List view'|translate}</span>
                        </button>
                    </li>
		{/if}
            </ul>
        </div>
    </div>
</nav>
