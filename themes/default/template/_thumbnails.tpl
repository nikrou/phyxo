<div class="thumbnails">
    {foreach $thumbnails as $thumbnail}
	{assign var=derivative value=$pwg->derivative($derivative_params, $thumbnail.src_image)}
	<div class="wrapper-thumbnail">
	    <div class="thumbnail">
		<a class="media" href="{$thumbnail.URL}">
		    <img {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="./themes/default/images/img_small.png" data-src="{$derivative->get_url()}"{/if} data-id="{$thumbnail.id}" data-ratio="{$derivative->get_ratio()}" alt="{$thumbnail.TN_ALT}" title="{$thumbnail.TN_TITLE}">
		</a>
		<div class="caption">
		    {if $SHOW_THUMBNAIL_CAPTION}
			<h5>{$thumbnail.NAME}</h5>
			{if !empty($thumbnail.icon_ts)}
			    <i class="fa fa-exclamation" title="{$cat.icon_ts.TITLE}"></i>
			{/if}
			{if isset($thumbnail.NB_COMMENTS)}
			    <span class="{if 0==$thumbnail.NB_COMMENTS}zero {/if}nb-comments">
				{$thumbnail.NB_COMMENTS|translate_dec:'%d comment':'%d comments'}
			    </span>
			{/if}

			{if isset($thumbnail.NB_HITS)}
			    <span class="{if 0==$thumbnail.NB_HITS}zero {/if}nb-hits">
				{$thumbnail.NB_HITS|translate_dec:'%d hit':'%d hits'}
			    </span>
			{/if}
		    {/if}
		</div>
	    </div>
	</div>
    {/foreach}
</div>
