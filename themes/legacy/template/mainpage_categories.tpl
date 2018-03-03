{extends file="__layout.tpl"}

{if $derivative_params}
    {strip}{html_style}
    .thumbnailCategory .illustration{ldelim}
    width: {$derivative_params->max_width()+5}px;
    }

    .content .thumbnailCategory .description{ldelim}
    height: {$derivative_params->max_height()+5}px;
    }
   {/html_style}{/strip}
{/if}

{footer_script}
var error_icon = "themes/legacy/images/errors_small.png", max_requests = {$maxRequests};
{/footer_script}

{block name="content"}
    {include file="_albums.tpl"}
{/block}
