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
	{*<!--{strip}{if isset($first)}
	   <a href="{$first.U_IMG}" title="{'First'|translate} : {$first.TITLE}" class="pwg-state-default pwg-button">
	   <span class="pwg-icon pwg-icon-arrowstop-w">&nbsp;</span><span class="pwg-button-text">{'First'|translate}</span>
	   </a>
	   {else}
	   <span class="pwg-state-disabled pwg-button">
	   <span class="pwg-icon pwg-icon-arrowstop-w">&nbsp;</span><span class="pwg-button-text">{'First'|translate}</span>
	   </span>
	   {/if}{/strip}-->*}
	{strip}{if isset($previous)}
	    <a href="{$previous.U_IMG}" title="{'Previous'|translate} : {$previous.TITLE_ESC}" id="navPrevPicture">
		<i class="fa fa-chevron-left" aria-hidden="true"></i>
	    </a>
	{else}
            <i class="fa fa-chevron-left" aria-hidden="true"></i>
	{/if}{/strip}
	{strip}{if isset($U_UP) and !isset($slideshow)}
            <a href="{$U_UP}" title="{'Thumbnails'|translate}">
		<i class="fa fa-chevron-up"></i>
            </a>
	{/if}{/strip}
	{strip}{if !isset($slideshow) && ($theme_config->photoswipe && !empty($thumbnails))}
            <a href="javascript:;" title="{'Fullscreen'|translate}" id="startPhotoSwipe">
		<i class="fa fa-arrows-alt" aria-hidden="true"></i>
            </a>
	{/if}{/strip}
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
	{strip}{if isset($next)}
	    <a href="{$next.U_IMG}" title="{'Next'|translate} : {$next.TITLE_ESC}" id="navNextPicture">
		<i class="fa fa-chevron-right" aria-hidden="true"></i>
	    </a>
	{else}
	    <i class="fa fa-chevron-right" aria-hidden="true"></i>
	{/if}{/strip}
	{*<!--{strip}{if isset($last)}
	   <a href="{$last.U_IMG}" title="{'Last'|translate} : {$last.TITLE}" class="pwg-state-default pwg-button pwg-button-icon-right">
	   <span class="pwg-icon pwg-icon-arrowstop-e"></span><span class="pwg-button-text">{'Last'|translate}</span>
	   </a>
	   {else}
	   <span class="pwg-state-disabled pwg-button pwg-button-icon-right">
	   <span class="pwg-icon pwg-icon-arrowstop-e">&nbsp;</span><span class="pwg-button-text">{'Last'|translate}</span>
	   </span>
	   {/if}{/strip}-->*}
    {/if}
</div>
