{foreach $thumbnails as $thumbnail}
    {assign var=derivative value=$pwg->derivative($derivative_params, $thumbnail.src_image)}
    {if !$derivative->is_cached()}
	{combine_script id='jquery.ajaxmanager' path='themes/default/js/plugins/jquery.ajaxmanager.js' load='footer'}
	{combine_script id='thumbnails.loader' path='themes/default/js/thumbnails.loader.js' require='jquery.ajaxmanager' load='footer'}
    {/if}
    <li>
	<span class="wrap1">
	    <span class="wrap2">
		<a href="{$thumbnail.URL}">
		    <img class="thumbnail" {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="{$ROOT_URL}{$themeconf.icon_dir}/img_small.png" data-src="{$derivative->get_url()}"{/if} alt="{$thumbnail.TN_ALT}" title="{$thumbnail.TN_TITLE}">
		</a>
	    </span>
	    {if $SHOW_THUMBNAIL_CAPTION }
		<span class="thumbLegend">
		    <span class="thumbName">{$thumbnail.NAME}</span>
		    {if !empty($thumbnail.icon_ts)}
			<img title="{$thumbnail.icon_ts.TITLE}" src="{$ROOT_URL}{$themeconf.icon_dir}/recent.png" alt="(!)">
		    {/if}
		    {if isset($thumbnail.NB_COMMENTS)}
			<span class="{if 0==$thumbnail.NB_COMMENTS}zero {/if}nb-comments">
			    <br>
			    {$pwg->l10n_dec('%d comment', '%d comments',$thumbnail.NB_COMMENTS)}
			</span>
		    {/if}

		    {if isset($thumbnail.NB_HITS)}
			<span class="{if 0==$thumbnail.NB_HITS}zero {/if}nb-hits">
			    <br>
			    {$pwg->l10n_dec('%d hit', '%d hits',$thumbnail.NB_HITS)}
			</span>
		    {/if}
		</span>
	    {/if}
	</span>
    </li>
{/foreach}
