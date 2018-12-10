<div class="loader"><img src="{$ROOT_URL}themes/legacy/images/ajax_loader.gif" alt="!"></div>
<ul class="thumbnailCategories">
    {foreach $category_thumbnails as $cat}
	{assign var=derivative value=$pwg->derivative($derivative_album_params, $cat.representative.src_image)}
	{if !$derivative->is_cached()}
	    {combine_script id='jquery.ajaxmanager' path='themes/legacy/js/plugins/jquery.ajaxmanager.js' load='footer'}
	    {combine_script id='thumbnails.loader' path='themes/legacy/js/thumbnails.loader.js' require='jquery.ajaxmanager' load='footer'}
	{/if}
	<li class="{if $cat@index is odd}odd{else}even{/if}">
	    <div class="thumbnailCategory">
		<div class="illustration">
		    <a href="{$cat.URL}">
			<img {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="themes/legacy/images/img_small.png" data-src="{$derivative->get_url()}"{/if} alt="{$cat.TN_ALT}" title="{$cat.NAME|@replace:'"':' '|@strip_tags:false} - {'display this album'|translate}">
		    </a>
		</div>
		<div class="description">
		    <h3>
			<a href="{$cat.URL}">{$cat.NAME}</a>
			{if !empty($cat.icon_ts)}
			    <img title="{$cat.icon_ts.TITLE}" src="themes/legacy/images/recent{if $cat.icon_ts.IS_CHILD_DATE}_by_child{/if}.png" alt="(!)">
			{/if}
		    </h3>
		    <div class="text">
			{if isset($cat.INFO_DATES) }
			    <p class="dates">{$cat.INFO_DATES}</p>
			{/if}
			<p class="Nb_images">{$cat.CAPTION_NB_IMAGES}</p>
			{if not empty($cat.DESCRIPTION)}
			    <p>{$cat.DESCRIPTION}</p>
			{/if}
		    </div>
		</div>
	    </div>
	</li>
    {/foreach}
</ul>
