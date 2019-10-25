{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Batch Manager'|translate}</a></li>
    <li class="breadcrumb-item">{'unit mode'|translate}</li>
{/block}

{block name="head_assets" append}
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/ui/theme/jquery.ui.core.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/plugins/selectize.clear.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/ui/theme/jquery.ui.datepicker.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/ui/theme/jquery.ui.timepicker-addon.css">
{/block}

{block name="footer_assets" prepend}
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.core.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.widget.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.mouse.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/jquery.colorbox.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.datepicker.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/selectize.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/LocalStorageCache.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.datepicker.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.timepicker-addon.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/i18n/jquery.ui.datepicker-{$lang_info.jquery_code}.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/i18n/jquery.ui.timepicker-{$lang_info.jquery_code}.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/datepicker.js"></script>
    <script>
     {* <!-- TAGS --> *}
     var tagsCache = new TagsCache({
	 serverKey: '{$CACHE_KEYS.tags}',
	 serverId: '{$CACHE_KEYS._hash}',
	 rootUrl: '{$ROOT_URL}'
     });

     tagsCache.selectize(jQuery('[data-selectize=tags]'), { lang: {
	 'Add': '{'Create'|translate}'
     }});

     $(function() {
	 $('[data-datepicker]').pwgDatepicker({
	     showTimepicker: true,
	     cancelButton: '{'Cancel'|translate}'
	 });

	 $("a.preview-box").colorbox();
     });
    </script>
{/block}

{block name="content"}
    <form action="{$F_ACTION}" method="POST">
	<div class="fieldset">
	    <h3>{'Display options'|translate}</h3>
	    <p>{'photos per page'|translate} :
		<a href="{$F_ACTION}?display=5">5</a>
		| <a href="{$F_ACTION}?display=10">10</a>
		| <a href="{$F_ACTION}?display=50">50</a>
	    </p>
	</div>

	{if !empty($navbar) }{include file="navigation_bar.tpl"}{/if}

	{if !empty($elements) }
	    <div><input type="hidden" name="element_ids" value="{$ELEMENT_IDS}"></div>
	    {foreach $elements as $element}
		<div class="fieldset">
		    <h3>{$element.LEGEND}</h3>

		    <p>
			<a href="{$element.FILE_SRC}" class="preview-box icon-zoom-in" title="{$element.LEGEND|@htmlspecialchars}"><img src="{$element.TN_SRC}" alt=""></a>
			<a class="btn btn-edit" href="{$element.U_EDIT}"><i class="fa fa-pencil"></i>{'Edit'|translate}</a>
		    </p>

		    <p>
			<label for="name-{$element.id}">{'Title'|translate}</label>
			<input class="form-control" type="text" name="name-{$element.id}" id="name-{$element.id}" value="{$element.NAME}">
		    </p>

		    <p>
			<label for="author-{$element.id}">{'Author'|translate}</label>
			<input class="form-control" type="text" name="author-{$element.id}" id="author-{$element.id}" value="{$element.AUTHOR}">
		    </p>

		    <p>
			<label>{'Creation date'|translate}</label>
			<input type="hidden" name="date_creation-{$element.id}" value="{$element.DATE_CREATION}">
			<label>
			    <i class="fa fa-calendar"></i>
			    <input type="text" data-datepicker="date_creation-{$element.id}" data-datepicker-unset="date_creation_unset-{$element.id}" readonly>
			</label>
			<a href="#" id="date_creation_unset-{$element.id}"><i class="fa fa-times-circle"></i>{'unset'|translate}</a>
		    </p>

		    <p>
			<label for="level-{$element.id}">{'Who can see this photo?'|translate}</label>
			<select class="custom-select" name="level-{$element.id}" id="level-{$element.id}">
			    {html_options options=$level_options selected=$element.LEVEL}
			</select>
		    </p>

		    <p>
			<label for="tags-{$element.id}">{'Tags'|translate}</label>
			<select data-selectize="tags" data-value="{$element.TAGS|@json_encode|escape:html}"
				placeholder="{'Type in a search term'|translate}" data-create="true" id="tags-{$element.id} name="tags-{$element.id}[]" multiple></select>
		    </p>

		    <p>
			<label for="description-{$element.id}">{'Description'|translate}</label>
			<textarea cols="50" rows="5" name="description-{$element.id}" id="description-{$element.id}" class="form-control">{$element.DESCRIPTION}</textarea>
		    </p>
		</div>
	    {/foreach}

	    {if !empty($navbar)}{include file="navigation_bar.tpl"}{/if}

	    <p>
		<input type="submit" class="btn btn-submit" value="{'Submit'|translate}" name="submit">
		<input type="reset" class="btn btn-reset" value="{'Reset'|translate}">
	    </p>
	{/if}
    </form>
{/block}
