<div class="row">
    {* this might sound ridiculous, but we want to fit the thumbnails to 90% of col-xs-12 without them being too blurry *}
    {define_derivative name='album_derivative_params' width=520 height=360 crop=true}
    {define_derivative name='album_derivative_params_square' type=IMG_SQUARE}

    {foreach $category_thumbnails as $cat}
	{if $theme_config->category_wells == 'never'}
	    {assign var="album_derivative" value=$pwg->derivative($album_derivative_params, $cat.representative.src_image)}
	    {* this needs a fixed size else it messes up the grid on tablets *}
	    {include file="grid_classes.tpl" width=260 height=180}
	    <div class="col-outer mt-3 {if $smarty.cookies.view == 'list'}col-12{else}{$col_class}{/if}" data-grid-classes="{$col_class}">
		<div class="card card-thumbnail">
		    <div class="h-100">
			<a href="{$cat.URL}" class="ripple{if $smarty.cookies.view != 'list'} d-block{/if}">
			    <img class="{if $smarty.cookies.view == 'list'}card-img-left{else}card-img-top{/if}" {if $album_derivative->is_cached()}src="{$album_derivative->get_url()}"{else}src="{$ROOT_URL}themes/treflez/img/transparent.png" data-src="{$album_derivative->get_url()}"{/if} alt="{$cat.TN_ALT}" title="{$cat.NAME|@replace:'"':' '|@strip_tags:false} - {'display this album'|translate}">
			</a>
			<div class="card-body">
			    <h5 class="card-title ellipsis {if !empty($cat.icon_ts)} recent{/if}">
				<a href="{$cat.URL}">{$cat.NAME}</a>
				{if !empty($cat.icon_ts)}
				    <img title="{$cat.icon_ts.TITLE}" src="{$ROOT_URL}{$themeconf.icon_dir}/recent{if $cat.icon_ts.IS_CHILD_DATE}_by_child{/if}.png" alt="(!)">
				{/if}
			    </h5>
			    <div class="card-text">
				{if not empty($cat.DESCRIPTION)}
				    <div class="description {if $theme_config->cat_descriptions} d-block{/if}">{$cat.DESCRIPTION}</div>
				{/if}
				{if isset($cat.INFO_DATES) }
				    <div class="info-dates">{$cat.INFO_DATES}</div>
				{/if}
			    </div>
			</div>
			{if $theme_config->cat_nb_images}
			    <div class="card-footer text-muted"><div class="d-inline-block ellipsis">{str_replace('<br>', ', ', $cat.CAPTION_NB_IMAGES)}</div></div>
			{/if}
		    </div>
		</div>
	    </div>
	{else}
	    {assign var="album_derivative_square" value=$pwg->derivative($album_derivative_params_square, $cat.representative.src_image)}
	    <div class="col-outer col-12">
		<div class="card">
		    <div class="card-body p-0">
			<a href="{$cat.URL}">
			    <div class="media h-100">
				<img class="d-flex mr-3" {if $album_derivative_square->is_cached()}src="{$album_derivative_square->get_url()}"{else}src="{$ROOT_URL}themes/treflez/img/transparent.png" data-src="{$album_derivative_square->get_url()}"{/if} alt="{$cat.TN_ALT}">
				<div class="media-body pt-2">
				    <h4 class="mt-0 mb-1">{$cat.NAME}</h4>
				    {if not empty($cat.DESCRIPTION)}
					<div class="description">{$cat.DESCRIPTION}</div>
				    {/if}
				    {if isset($cat.INFO_DATES) }
					<div>{$cat.INFO_DATES}</div>
				    {/if}
				    {if $theme_config->cat_nb_images}
					<div class="text-muted">{str_replace('<br>', ', ', $cat.CAPTION_NB_IMAGES)}</div>
				    {/if}
				</div>
			    </div>
			</a>
		    </div>
		</div>
	    </div>
	{/if}
    {/foreach}
</div>
