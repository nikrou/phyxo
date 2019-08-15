<div id="navigationButtons" class="col-12 py-2">
    {if $DISPLAY_NAV_BUTTONS or isset($slideshow)}
	{if isset($slideshow)}
	    {if isset($slideshow.U_INC_PERIOD)}
		<a href="{$slideshow.U_INC_PERIOD}" title="{'Reduce diaporama speed'|translate}" class="pwg-state-default pwg-button">
		    <i class="fa fa-minus" aria-hiden="true"></i>
		</a>
	    {else}
		<i class="fa fa-minus" aria-hiden="true"></i>
	    {/if}
	    {if isset($slideshow.U_DEC_PERIOD)}
		<a href="{$slideshow.U_DEC_PERIOD}" title="{'Accelerate diaporama speed'|translate}" class="pwg-state-default pwg-button">
		    <i class="fa fa-plus" aria-hidden="true"></i>
		</a>
	    {else}
		<i class="fa fa-plus" aria-hidden="true"></i>
	    {/if}
	{/if}
	{if isset($slideshow.U_START_REPEAT)}
	    <a href="{$slideshow.U_START_REPEAT}" title="{'Repeat the slideshow'|translate}" class="pwg-state-default pwg-button">
		<i class="fa fa-repeat" aria-hidden="true"></i>
	    </a>
	{/if}
	{* TODO need an icon for this
	   {if isset($slideshow.U_STOP_REPEAT)}
	   <a href="{$slideshow.U_STOP_REPEAT}" title="{'Not repeat the slideshow'|translate}" class="pwg-state-default pwg-button">
	   <span class="pwg-icon pwg-icon-repeat-stop"></span>
	   </a>
	   {/if} *}
	{if isset($first)}
	    <a href="{$first.U_IMG}" title="{'First'|translate}{if isset($first.TITLE)} : {$first.TITLE}{/if}">
		<i class="fa fa-fast-backward" aria-hidden="true"></i>
	    </a>
	{else}
	    <i class="fa fa-fast-backward" aria-hidden="true"></i>
	{/if}

	{if isset($previous)}
	    <a href="{$previous.U_IMG}" title="{'Previous'|translate}{if isset($previous.TITLE_ESC)} : {$previous.TITLE_ESC}{/if}" id="navPrevPicture">
		<i class="fa fa-chevron-left" aria-hidden="true"></i>
	    </a>
	{else}
            <i class="fa fa-chevron-left" aria-hidden="true"></i>
	{/if}
	{if isset($U_UP) and !isset($slideshow)}
            <a href="{$U_UP}" title="{'Thumbnails'|translate}">
		<i class="fa fa-chevron-up" aria-hidden="true"></i>
            </a>
	{/if}
	{if !isset($slideshow) && ($theme_config->photoswipe && !empty($thumbnails))}
            <button type="button" class="btn btn-link" title="{'Fullscreen'|translate}" id="startPhotoSwipe">
		<i class="fa fa-arrows-alt" aria-hidden="true"></i>
            </button>
	{/if}
	{if isset($slideshow.U_START_PLAY)}
	    <a href="{$slideshow.U_START_PLAY}" title="{'Play of slideshow'|translate}">
		<i class="fa fa-play" aria-hidden="true"></i>
	    </a>
	{/if}
	{if isset($slideshow.U_STOP_PLAY)}
	    <a href="{$slideshow.U_STOP_PLAY}" title="{'Pause of slideshow'|translate}">
		<i class="fa fa-pause" aria-hidden="true"></i>
	    </a>
	{/if}
	{if isset($U_SLIDESHOW_STOP) }
            <a href="{$U_SLIDESHOW_STOP}" title="{'stop the slideshow'|translate}">
		<i class="fa fa-stop" aria-hidden="true"></i>
            </a>
	{/if}

	{if isset($next)}
	    <a href="{$next.U_IMG}" title="{'Next'|translate}{if isset($next.TITLE_ESC)} : {$next.TITLE_ESC}{/if}" id="navNextPicture">
		<i class="fa fa-chevron-right" aria-hidden="true"></i>
	    </a>
	{else}
	    <i class="fa fa-chevron-right" aria-hidden="true"></i>
	{/if}

	{if isset($last)}
	    <a href="{$last.U_IMG}" title="{'Last'|translate}{if isset($last.TITLE)} : {$last.TITLE}{/if}">
		<i class="fa fa-fast-forward" aria-hidden="true"></i>
	    </a>
	{else}
	    <i class="fa fa-fast-forward" aria-hidden="true"></i>
	{/if}
    {/if}
</div>
