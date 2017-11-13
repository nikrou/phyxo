<div class="thumbnails">
    {foreach $thumbnails as $thumbnail}
	{assign var=derivative value=$pwg->derivative($derivative_params, $thumbnail.src_image)}
	<div class="thumbnail">
	    <div class="media">
		<a href="{$thumbnail.URL}">
		    <img {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="./themes/default/images/img_small.png" data-src="{$derivative->get_url()}"{/if} alt="{$thumbnail.TN_ALT}" title="{$thumbnail.TN_TITLE}">
		</a>
	    </div>
	    <div class="caption">
		{if $SHOW_THUMBNAIL_CAPTION }
		    <span class="thumbName">{$thumbnail.NAME}</span>
		    {if !empty($thumbnail.icon_ts)}
			<i class="fa fa-exclamation" title="{$cat.icon_ts.TITLE}"></i>
		    {/if}
		    {if isset($thumbnail.NB_COMMENTS)}
			<span class="{if 0==$thumbnail.NB_COMMENTS}zero {/if}nb-comments">
			    {$pwg->l10n_dec('%d comment', '%d comments',$thumbnail.NB_COMMENTS)}
			</span>
		    {/if}

		    {if isset($thumbnail.NB_HITS)}
			<span class="{if 0==$thumbnail.NB_HITS}zero {/if}nb-hits">
			    {$pwg->l10n_dec('%d hit', '%d hits',$thumbnail.NB_HITS)}
			</span>
		    {/if}
		{/if}
	    </div>
	</div>
    {/foreach}
</div>
