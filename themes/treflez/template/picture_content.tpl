{if preg_match("/(mp4|m4v)$/", $current.path)}
    {if $current.height < $current.width}
	<div id="video-modal" class="col-lg-8 col-md-10 col-sm-12 mx-auto">
	    {if $current.height / $current.width * 100 < 60}
		<div class="embed-responsive embed-responsive-16by9">
	    {else}
		    <div class="embed-responsive embed-responsive-custom" style="padding-bottom:{$current.height / $current.width * 100}%">
	    {/if}
    {else}
	    <div id="video-modal" class="col-lg-3 col-md-5 col-sm-6 col-xs-8 mx-auto">
		<div class="embed-responsive embed-responsive-9by16">
    {/if}
    <video id="video" class="embed-responsive-item" width="100%" height="auto" controls preload="auto" poster="{$current.selected_derivative->getUrl()}">
	<source src="{$ROOT_URL}{$current.path}" type="video/mp4"></source>
    </video>
		</div>
	    </div>
{else}
	    <img {if $current.selected_derivative->is_cached()}src="{$current.selected_derivative->getUrl()}" {$current.selected_derivative->get_size_htm()}{else}src="{$ROOT_URL}themes/treflez/img/transparent.png" data-src="{$current.selected_derivative->getUrl()}"{/if} alt="{if isset($ALT_IMG)}$ALT_IMG{/if}" id="theMainImage" usemap="#map{$current.selected_derivative->get_type()}" title="{if isset($COMMENT_IMG)}{$COMMENT_IMG|@strip_tags:false|@replace:'"':' '}{else}{if isset($current.TITLE_ESC)}$current.TITLE_ESC}{/if}{if isset($ALT_IMG)} - {$ALT_IMG}{/if}{/if}">

	    {foreach $current.unique_derivatives as $derivative}{strip}
		<map name="map{$derivative->get_type()}">
		    {assign var='size' value=$derivative->get_size()}
		    {if isset($previous)}
			<area shape=rect coords="0,0,{($size[0]/4)|@intval},{$size[1]}" href="{$previous.U_IMG}" title="{'Previous'|translate}{if isset($previous.TITLE_ESC)} : {$previous.TITLE_ESC}{/if}" alt="{if isset($previous.TITLE_ESC)}{$previous.TITLE_ESC}{/if}">
		    {/if}
		    <area shape=rect coords="{($size[0]/4)|@intval},0,{($size[0]/1.34)|@intval},{($size[1]/4)|@intval}" href="{$U_UP}" title="{'Thumbnails'|translate}" alt="{'Thumbnails'|translate}">
		    {if isset($next)}
			<area shape=rect coords="{($size[0]/1.33)|@intval},0,{$size[0]},{$size[1]}" href="{$next.U_IMG}" title="{'Next'|translate}{if isset($next.TITLE_ESC)} : {$next.TITLE_ESC}{/if}" alt="{if isset($next.TITLE_ESC)}{$next.TITLE_ESC}{/if}">
		    {/if}
		</map>
{/strip}{/foreach}
{/if}
