{if $derivative_params->type == "thumb"}
    {assign var=width value=520}
    {assign var=height value=360}
    {assign var=rwidth value=260}
    {assign var=rheight value=180}
{else}
    {assign var=width value=$derivative_params->sizing->ideal_size[0]}
    {assign var=height value=$derivative_params->sizing->ideal_size[1]}
    {assign var=rwidth value=$width}
    {assign var=rheight value=$height}
{/if}

{define_derivative name='derivative_params' width=$width height=$height crop=true}
{assign var=idx value=0+$START_ID}
<div class="row">
    {foreach $thumbnails as $thumbnail}
	{assign var=derivative value=$pwg->derivative($derivative_params, $thumbnail.src_image)}
	{include file="grid_classes.tpl" width=$rwidth height=$rheight}
	<div class="col-outer {if $smarty.cookies.view == 'list'}col-12{else}{$col_class}{/if}" data-grid-classes="{$col_class}">
	    <div class="card card-thumbnail">
		<div class="h-100">
		    <a href="{$thumbnail.URL}" data-index="{$idx}" class="ripple{if $smarty.cookies.view != 'list'} d-block{/if}">
			<img class="{if $smarty.cookies.view == 'list'}card-img-left{else}card-img-top{/if}" {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="{$ROOT_URL}themes/treflez/img/transparent.png" data-src="{$derivative->get_url()}"{/if} alt="{$thumbnail.TN_ALT}" title="{$thumbnail.TN_TITLE}">
			{if isset($loaded_plugins['UserCollections']) && !isset($U_LOGIN)}
			    <div class="addCollection" data-id="{$thumbnail.id}" data-cols="[{$thumbnail.COLLECTIONS}]"><i class="fa fa-star"></i><span class="ml-2">{'Collections'|translate}</span></div>
			{/if}
		    </a>
		    {assign var=idx value=$idx+1}
		    {if $SHOW_THUMBNAIL_CAPTION}
			<div class="card-body{if !$theme_config->thumbnail_caption && $smarty.cookies.view != 'list'} d-none{/if}{if !$theme_config->thumbnail_caption} list-view-only{/if}">
			    <h6 class="card-title">
				<a href="{$thumbnail.URL}" class="ellipsis{if !empty($thumbnail.icon_ts)} recent{/if}">{$thumbnail.NAME}</a>
				{if !empty($thumbnail.icon_ts)}
				    <img title="{$thumbnail.icon_ts.TITLE}" src="{$ROOT_URL}{$themeconf.icon_dir}/recent.png" alt="(!)">
				{/if}
			    </h6>
			    {if isset($thumbnail.NB_COMMENTS) || isset($thumbnail.NB_HITS)}
				<div class="card-text">
				    {if isset($thumbnail.NB_COMMENTS)}
					<p class="text-muted {if 0==$thumbnail.NB_COMMENTS}zero {/if}nb-comments">
					    {$thumbnail.NB_COMMENTS|translate_dec:'%d comment':'%d comments'}
					</p>
				    {/if}
				    {if isset($thumbnail.NB_HITS)}
					<p class="text-muted {if 0==$thumbnail.NB_HITS}zero {/if}nb-hits">
					    {$thumbnail.NB_HITS|translate_dec:'%d hit':'%d hits'}
					</p>
				    {/if}
				</div>
			    {/if}
			</div>
		    {/if}
		</div>
	    </div>
	</div>
    {/foreach}
</div>
