{extends file="__layout.tpl"}

{block name="content"}
    {include file='include/autosize.inc.tpl'}
    {include file='include/datepicker.inc.tpl'}
    {include file='include/colorbox.inc.tpl'}

    {combine_script id='LocalStorageCache' load='footer' path='admin/theme/js/LocalStorageCache.js'}

    {combine_script id='jquery.selectize' load='footer' path='admin/theme/js/plugins/selectize.js'}
    {combine_css id='jquery.selectize' path="admin/theme/js/plugins/selectize.clear.css"}

    {footer_script}
    (function(){
    {* <!-- CATEGORIES --> *}
    var categoriesCache = new CategoriesCache({
    serverKey: '{$CACHE_KEYS.categories}',
    serverId: '{$CACHE_KEYS._hash}',
    rootUrl: '{$ROOT_URL}'
    });

    categoriesCache.selectize(jQuery('[data-selectize=categories]'));

    {* <!-- TAGS --> *}
    var tagsCache = new TagsCache({
    serverKey: '{$CACHE_KEYS.tags}',
    serverId: '{$CACHE_KEYS._hash}',
    rootUrl: '{$ROOT_URL}'
    });

    tagsCache.selectize(jQuery('[data-selectize=tags]'), { lang: {
    'Add': '{'Create'|translate}'
    }});

    {* <!-- DATEPICKER --> *}
    jQuery(function(){ {* <!-- onLoad needed to wait localization loads --> *}
    jQuery('[data-datepicker]').pwgDatepicker({
    showTimepicker: true,
    cancelButton: '{'Cancel'|translate}'
    });
    });

    {* <!-- THUMBNAILS --> *}
    jQuery("a.preview-box").colorbox();
    }());
    {/footer_script}

    <h2>{$TITLE} &#8250; {'Edit photo'|translate} {$TABSHEET_TITLE}</h2>

    <form action="{$F_ACTION}" method="post" id="catModify">

	<fieldset>
	    <legend>{'Informations'|translate}</legend>

	    <table>

		<tr>
		    <td id="albumThumbnail">
			<a href="{$FILE_SRC}" class="preview-box icon-zoom-in" title="{$TITLE|htmlspecialchars}"><img src="{$TN_SRC}" alt="{'Thumbnail'|translate}"></a>
		    </td>
		    <td id="albumLinks" style="width:400px;vertical-align:top;">
			<ul style="padding-left:15px;margin:0;">
			    <li>{$INTRO.file}</li>
			    <li>{$INTRO.add_date}</li>
			    <li>{$INTRO.added_by}</li>
			    <li>{$INTRO.size}</li>
			    <li>{$INTRO.stats}</li>
			    <li>{$INTRO.id}</li>
			</ul>
		    </td>
		    <td class="photoLinks">
			<ul>
			    {if isset($U_JUMPTO) }
				<li><a href="{$U_JUMPTO}"><i class="fa fa-eye"></i> {'jump to photo'|translate}</a></li>
			    {/if}
			    {if !url_is_remote($PATH)}
				<li><a href="{$U_SYNC}"><i class="fa fa-exchange"></i> {'Synchronize metadata'|translate}</a></li>

				<li><a href="{$U_DELETE}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');"><i class="fa fa-trash"></i> {'delete photo'|translate}</a></li>
			    {/if}
			</ul>
		    </td>
		</tr>
	    </table>

	</fieldset>

	<fieldset>
	    <legend>{'Properties'|translate}</legend>

	    <p>
		<strong>{'Title'|translate}</strong>
		<br>
		<input type="text" class="large" name="name" value="{$NAME|@escape}">
	    </p>

	    <p>
		<strong>{'Author'|translate}</strong>
		<br>
		<input type="text" class="large" name="author" value="{$AUTHOR}">
	    </p>

	    <p>
		<strong>{'Creation date'|translate}</strong>
		<br>
		<input type="hidden" name="date_creation" value="{$DATE_CREATION}">
		<label>
		    <i class="fa fa-calendar"></i>
		    <input type="text" data-datepicker="date_creation" data-datepicker-unset="date_creation_unset" readonly>
		</label>
		<a href="#" id="date_creation_unset"><i class="fa fa-times-circle"></i> {'unset'|translate}</a>
	    </p>

	    <p>
		<strong>{'Linked albums'|translate}</strong>
		<br>
		<select data-selectize="categories" data-value="{$associated_albums|@json_encode|escape:html}"
			placeholder="{'Type in a search term'|translate}"
			data-default="{$STORAGE_ALBUM}" name="associate[]" multiple style="width:600px;"></select>
	    </p>

	    <p>
		<label>{'Representation of albums'|translate}
		    <select data-selectize="categories" data-value="{$represented_albums|@json_encode|escape:html}"
					    placeholder="{'Type in a search term'|translate}"
					    name="represent[]" multiple style="width:600px;"></select>
		</label>
	    </p>

	    <p>
		<label>{'Tags'|translate}
		    <select data-selectize="tags" data-value="{$tag_selection|@json_encode|escape:html}"
					    placeholder="{'Type in a search term'|translate}"
					    data-create="true" name="tags[]" multiple style="width:600px;"></select>
		</label>
	    </p>

	    <p>
		<strong>{'Description'|translate}</strong>
		<br>
		<textarea name="description" id="description" class="description">{$DESCRIPTION}</textarea>
	    </p>

	    <p>
		<strong>{'Who can see this photo?'|translate}</strong>
		<br>
		<select name="level" size="1">
		    {html_options options=$level_options selected=$level_options_selected}
		</select>
	    </p>

	    <p style="margin:40px 0 0 0">
		<input class="submit" type="submit" value="{'Save Settings'|translate}" name="submit">
	    </p>
	</fieldset>

    </form>
{/block}
