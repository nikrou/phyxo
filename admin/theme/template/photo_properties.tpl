{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Image'|translate}</a></li>
    <li class="breadcrumb-item">{'Properties'|translate}</li>
{/block}

{block name="head_assets" append}
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/plugins/selectize.clear.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/ui/theme/jquery.ui.theme.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/ui/theme/jquery.ui.datepicker.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/ui/theme/jquery.ui.timepicker-addon.css">
{/block}

{block name="footer_assets" prepend}
    <script src="{$ROOT_URL}admin/theme/js/plugins/jquery.colorbox.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.core.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.widget.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.mouse.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.slider.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/LocalStorageCache.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/selectize.js"></script>
    <script>
     {* <!-- CATEGORIES --> *}
     var categoriesCache = new CategoriesCache({
	 serverKey: '{$CACHE_KEYS.categories}',
	 serverId: '{$CACHE_KEYS._hash}',
	 rootUrl: '{$ROOT_URL}'
     });

     {* <!-- TAGS --> *}
     var tagsCache = new TagsCache({
	 serverKey: '{$CACHE_KEYS.tags}',
	 serverId: '{$CACHE_KEYS._hash}',
	 rootUrl: '{$ROOT_URL}'
     });

     $(function() {
	 categoriesCache.selectize($('[data-selectize=categories]'));
	 tagsCache.selectize($('[data-selectize=tags]'), { lang: {
	     'Add': '{'Create'|translate}'
	 }});
     });
    </script>
{/block}

{block name="content"}
    <div class="fieldset">
	<h3>{'Informations'|translate}</h3>

	<p>
	    <a href="{$FILE_SRC}" class="preview-box icon-zoom-in" title="{$TITLE|escape:'html'}"><img src="{$TN_SRC}" alt="{'Thumbnail'|translate}"></a>
	</p>

	<ul>
	    <li>{$INTRO.file}</li>
	    <li>{$INTRO.add_date}</li>
	    <li>{$INTRO.added_by}</li>
	    <li>{$INTRO.size}</li>
	    <li>{$INTRO.stats}</li>
	    <li>{$INTRO.id}</li>
	</ul>

	<div>
	    {if isset($U_JUMPTO) }
		<p><a href="{$U_JUMPTO}"><i class="fa fa-eye"></i> {'jump to photo'|translate}</a></p>
	    {/if}
	    {if !\Phyxo\Functions\URL::url_is_remote($PATH)}
		<p><a class="btn btn-success" href="{$U_SYNC}"><i class="fa fa-exchange"></i> {'Synchronize metadata'|translate}</a></p>
		<p>
		    <form action="{$U_DELETE}" method="post">
			<button type="submit" class="btn btn-delete" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');">
			    <i class="fa fa-trash"></i> {'delete photo'|translate}
			</button>
		    </form>
		</p>
	    {/if}
	</div>
    </div>

    <form action="{$F_ACTION}" method="post" id="catModify">
	<div class="fieldset">
	    <h3>{'Properties'|translate}</h3>
	    <p>
		<label>{'Title'|translate}</label>
		<input class="form-control" type="text" name="name" value="{$NAME|@escape}">
	    </p>

	    <p>
		<label>{'Author'|translate}</label>
		<input class="form-control" type="text" name="author" value="{$AUTHOR}">
	    </p>

	    <p>
		<label>{'Creation date'|translate}</label>
		<input type="date" name="date_creation" value="{$DATE_CREATION}">
	    </p>

	    <p>
		<label>{'Linked albums'|translate}</label>
		<select data-selectize="categories" data-value="{$associated_albums|json_encode|escape:'html'}"
					placeholder="{'Type in a search term'|translate}" data-default="{$STORAGE_ALBUM}" name="associate[]" multiple></select>
	    </p>

	    <p>
		<label for="album">{'Representation of albums'|translate}</label>
		<select id="album" data-selectize="categories" data-value="{$represented_albums|json_encode|escape:'html'}"
			placeholder="{'Type in a search term'|translate}" name="represent[]" multiple></select>
	    </p>

	    <p>
		<label>{'Tags'|translate}</label>
		<select data-selectize="tags" data-value="{$tag_selection|json_encode|escape:'html'}"
			placeholder="{'Type in a search term'|translate}" data-create="true" name="tags[]" multiple></select>
	    </p>

	    <p>
		<label for="description">{'Description'|translate}</label>
		<textarea name="description" id="description" class="form-control">{$DESCRIPTION}</textarea>
	    </p>

	    <p>
		<label for="level">{'Who can see this photo?'|translate}</label>
		<select id="level" class="custom-select" name="level" size="1">
		    {html_options options=$level_options selected=$level_options_selected}
		</select>
	    </p>

	    <p>
		<input class="btn btn-submit" type="submit" value="{'Save Settings'|translate}" name="submit">
	    </p>
	</div>
    </form>
{/block}
