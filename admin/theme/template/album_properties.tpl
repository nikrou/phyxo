{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_ALBUMS}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album'|translate}: {$CATEGORIES_NAV}</li>
    <li class="breadcrumb-item">{'Properties'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='LocalStorageCache' load='footer' path='admin/theme/js/LocalStorageCache.js'}

    {combine_script id='jquery.selectize' load='footer' path='admin/theme/js/plugins/selectize.js'}
    {combine_css id='jquery.selectize' path="admin/theme/js/plugins/selectize.clear.css"}

    {footer_script}
    {* <!-- CATEGORIES --> *}
    var categoriesCache = new CategoriesCache({
    serverKey: '{$CACHE_KEYS.categories}',
    serverId: '{$CACHE_KEYS._hash}',
    rootUrl: '{$ROOT_URL}'
    });

    categoriesCache.selectize($('[data-selectize=categories]'), {
    default: 0,
    filter: function(categories, options) {
    // remove itself and children
    var filtered = $.grep(categories, function(cat) {
    return !(/\b{$CAT_ID}\b/.test(cat.uppercats));
    });

    filtered.push({
    id: 0,
    fullname: '------------',
    global_rank: 0
    });

    return filtered;
    }
    });
   {/footer_script}

   <form class="vertical-form" action="{$F_ACTION}" method="POST" id="catModify">
       <div class="fieldset">
		<h3>{'Informations'|translate}</h3>

			<p>
				{if isset($representant) }
				{if isset($representant.picture) }
					<a href="{$representant.picture.URL}"><img src="{$representant.picture.SRC}" alt=""></a>
				{else}
					<img src="./theme/icon/category_representant_random.png" alt="{'Random photo'|translate}">
				{/if}

				{if $representant.ALLOW_SET_RANDOM }
					<p><input class="btn btn-submit" type="submit" name="set_random_representant" value="{'Refresh'|translate}" title="{'Find a new representant by random'|translate}"></p>
				{/if}

				{if isset($representant.ALLOW_DELETE) }
					<p><input class="btn btn-submit" type="submit" name="delete_representant" value="{'Delete Representant'|translate}"></p>
				{/if}
				{/if}
			</p>

			<p>{$INTRO}</p>
		    <ul>
			   {if \Phyxo\Functions\Category::cat_admin_access($CAT_ID)}
			       <li><a href="{$U_JUMPTO}"><i class="fa fa-eye"></i> {'jump to album'|translate} →</a></li>
			   {/if}

			   {if isset($U_MANAGE_ELEMENTS) }
			       <li><a href="{$U_MANAGE_ELEMENTS}"><i class="fa fa-photo"></i> {'manage album photos'|translate}</a></li>
			   {/if}

			   <li><a href="{$U_ADD_PHOTOS_ALBUM}"><i class="fa fa-plus-circle"></i> {'Add Photos'|translate}</a></li>
			   <li><a href="{$U_CHILDREN}"><i class="fa fa-sitemap"></i> {'manage sub-albums'|translate}</a></li>

			   {if isset($U_SYNC) }
			       <li><a href="{$U_SYNC}"><i class="fa fa-exchange"></i> {'Synchronize'|translate}</a> ({'Directory'|translate} = {$CAT_FULL_DIR})</li>
			   {/if}

			   {if isset($U_DELETE) }
			       <li><a href="{$U_DELETE}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');"><i class="fa fa-trash"></i> {'delete album'|translate}</a></li>
			   {/if}
		    </ul>
       </div>

       <div class="fieldset">
	   		<h3>{'Properties'|translate}</h3>
			<p>
				<label for="name">{'Name'|translate}</label>
				<input class="form-control" type="text" id="name" name="name" value="{$CAT_NAME}" maxlength="255">
			</p>

			<p>
				<label for="comment">{'Description'|translate}</label>
				<textarea class="form-control" cols="50" rows="5" name="comment" id="comment">{$CAT_COMMENT}</textarea>
			</p>

			{if isset($parent_category) }
				<p>
					<label for="categories">{'Parent album'|translate}</label>
					<select id="categories" data-selectize="categories" data-value="{$parent_category|@json_encode|escape:html}" name="parent" style="width:600px"></select>
				</p>
			{/if}

			<h3>{'Lock'|translate}</h3>
			<p>
				{html_radios name="visible" values=['true','true_sub','false'] output=['No'|translate,'No and unlock sub-albums'|translate,'Yes'|translate] selected=$CAT_VISIBLE}
			</p>

			{if isset($CAT_COMMENTABLE)}
				<h3>{'Comments'|translate}</h3>
				<p>
				{html_radios name="commentable" values=['false','true'] output=['No'|translate,'Yes'|translate] selected=$CAT_COMMENTABLE}
				<label>
					<input type="checkbox" name="apply_commentable_on_sub">
					{'Apply to sub-albums'|translate}
				</label>
				</p>
			{/if}

			<p>
				<input class="btn btn-submit" type="submit" value="{'Save Settings'|translate}" name="submit">
			</p>
       </div>
   </form>
{/block}
