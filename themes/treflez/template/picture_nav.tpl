<nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-2">
    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	{include file="_breadcrumb.tpl" elements=$TITLE}

        <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#secondary-navbar" aria-controls="secondary-navbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="fa fa-bars"></span>
        </button>
        <div class="navbar-collapse collapse justify-content-end" id="secondary-navbar">
            <ul class="navbar-nav">
		{if isset($current.unique_derivatives) && count($current.unique_derivatives)>1}
                    <li class="nav-item dropdown">
			<button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown" title="{'Photo sizes'|translate}">
                            <i class="fa fa-image" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Photo sizes'|translate}</span>
			</button>
			<div class="dropdown-menu dropdown-menu-right" role="menu">
			    {foreach $current.unique_derivatives as $derivative_type => $derivative}
				<button type="button" id="derivative{$derivative->get_type()}" class="dropdown-item derivative-li{if $derivative->get_type() == $current.selected_derivative->get_type()} active{/if}" data-action="changeImgSrc" data-url="{$derivative->getUrl()}" data-type-save="{$derivative_type}" data-type-map="{$derivative->get_type()}">
                                    {$derivative->get_type()|translate}
				    <span class="derivativeSizeDetails"> ({$derivative->get_size_hr()})</span>
				</button>
			    {/foreach}
			</div>
                    </li>
		{/if}
		{if isset($U_SLIDESHOW_START)}
                    <li class="nav-item">
			<a class="nav-link" href="{if $theme_config->photoswipe}javascript:;{else}{$U_SLIDESHOW_START}{/if}" title="{'slideshow'|translate}" id="startSlideshow" rel="nofollow">
                            <i class="fa fa-play" aria-hidden="true"></i><span class="d-lg-none ml-2 text-capitalize">{'slideshow'|translate}</span>
			</a>
                    </li>
		{/if}
		{if isset($U_METADATA)}
                    <li class="nav-item">
			<a class="nav-link" href="{$U_METADATA}" title="{'Show file metadata'|translate}" rel="nofollow">
			    <i class="fa fa-camera-retro" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Show file metadata'|translate}</span>
			</a>
                    </li>
		{/if}
		{if isset($current.U_DOWNLOAD)}
		    {if empty($current.formats)}
			<li class="nav-item">
			    <a id="downloadSwitchLink" class="nav-link" href="{$current.U_DOWNLOAD}" title="{'Download this file'|translate}" rel="nofollow">
				<i class="fa fa-download" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Download this file'|translate}</span>
			    </a>
		    {else}
			    <li class="nav-item dropdown">
				<button type="button" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" title="{'Download this file'|translate}">
				    <i class="fa fa-download" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Download this file'|translate}</span>
				</button>
				<ul class="dropdown-menu dropdown-menu-right" role="menu">
				    {foreach $current.formats as $format}
					<li class="dropdown-item"><a href="{$format.download_url}" rel="nofollow">{$format.label}<span class="downloadformatDetails"> ({$format.filesize})</span></a></li>
				    {/foreach}
				</ul>
		    {/if} {* has formats *}
			    </li>
		{/if}
		{if isset($favorite)}
                    <li class="nav-item">
			<a class="nav-link" href="{$favorite.U_FAVORITE}" data-action="addOrRemoveFavorite" title="{if $favorite.IS_FAVORITE}{'delete this photo from your favorites'|translate}{else}{'add this photo to your favorites'|translate}{/if}" rel="nofollow">
                            <i class="fa {if !$favorite.IS_FAVORITE}fa-heart-o{else}fa-heart{/if}"></i>
			</a>
                    </li>
		{/if}
		{if isset($U_SET_AS_REPRESENTATIVE)}
		    <li class="nav-item">
			<a class="nav-link" id="cmdSetRepresentative" href="{$U_SET_AS_REPRESENTATIVE}" title="{'set as album representative'|translate}" rel="nofollow">
                            <i class="fa fa-link" aria-hidden="true"></i><span class="d-lg-none ml-2">{'set as album representative'|translate}</span>
			</a>
                    </li>
		{/if}
		{if isset($U_PHOTO_ADMIN)}
                    <li class="nav-item">
			<a class="nav-link" id="cmdEditPhoto" href="{$U_PHOTO_ADMIN}" title="{'Modify information'|translate}" rel="nofollow">
                            <i class="fa fa-pencil" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Modify information'|translate}</span>
			</a>
                    </li>
		{/if}
		{if isset($U_CADDIE)}
                    <li class="nav-item">
			<a class="nav-link" href="{$U_CADDIE}" data-action="addToCaddie" data-id="{$current.id}" title="{'Add to caddie'|translate}" rel="nofollow">
                            <i class="fa fa-shopping-basket" aria-hidden="true"></i><span class="d-lg-none ml-2">{'Add to caddie'|translate}</span>
			</a>
                    </li>
		{/if}
		{if isset($PLUGIN_PICTURE_BUTTONS)}{foreach $PLUGIN_PICTURE_BUTTONS as $button}{$button}{/foreach}{/if}
		{if isset($PLUGIN_PICTURE_ACTIONS)}{$PLUGIN_PICTURE_ACTIONS}{/if}
            </ul>
        </div>
    </div>
</nav>

<div class="alert alert-dismissible fade show" role="alert" style="display:none">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <i class="fa fa-times"></i>
    </button>
</div>
