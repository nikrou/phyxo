{extends file="__layout.tpl"}

{*define_derivative name='derivative_params' width=160 height=90 crop=true*}
{if $derivative_params}
    {html_style}
    {*Set some sizes according to maximum thumbnail width and height*}
    .thumbnails SPAN,
    .thumbnails .wrap2 A,
    .thumbnails LABEL{ldelim}
    width: {$derivative_params->max_width()+2}px;
    }

    .thumbnails .wrap2{ldelim}
    height: {$derivative_params->max_height()+3}px;
    }
    {if $derivative_params->max_width() > 600}
	.thumbLegend {ldelim}font-size: 130%}
    {else}
	{if $derivative_params->max_width() > 400}
	    .thumbLegend {ldelim}font-size: 110%}
	{else}
	    .thumbLegend {ldelim}font-size: 90%}
	{/if}
    {/if}
    {/html_style}
{/if}

{footer_script}
var error_icon = "{$ROOT_URL}{$themeconf.icon_dir}/errors_small.png", max_requests = {$maxRequests};
{/footer_script}

{block name="content"}
    {if !empty($category_thumbnails)} {* display sub-albums *}
	{include file="_albums.tpl"}
    {/if}

    {if !empty($thumbnails)}
	{include file="_thumbnails.tpl"}
    {/if}
{/block}
