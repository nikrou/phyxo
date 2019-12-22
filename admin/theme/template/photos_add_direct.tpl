{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Upload Photos'|translate}</a></li>
    <li class="breadcrumb-item">{'Web Form'|translate}</li>
{/block}

{block name="head_assets" append}
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/plugins/jquery.jgrowl.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/plugins/plupload/jquery.plupload.queue/css/jquery.plupload.queue.css">
    <link rel="stylesheet" href="{$ROOT_URL}admin/theme/js/plugins/selectize.clear.css">
{/block}

{block name="footer_assets" prepend}
    <script src="{$ROOT_URL}admin/theme/js/LocalStorageCache.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/plupload/moxie.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/jquery.jgrowl.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/selectize.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/plupload/plupload.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/plupload/jquery.plupload.queue/jquery.plupload.queue.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/plupload/i18n/{$lang_info.plupload_code}.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/jquery.colorbox.js"></script>
    <script src="{$ROOT_URL}admin/theme/src/js/photos_add_direct.js"></script>

    <script>
     var categoriesCache = new CategoriesCache({
	 serverKey: '{$CACHE_KEYS.categories}',
	 serverId: '{$CACHE_KEYS._hash}',
	 rootUrl: '{$ROOT_URL}'
     });

     var ws_url = '{$ws}';
     var pwg_token = '{$csrf_token}';
     var u_edit_pattern = '{$U_EDIT_PATTERN}';
     var u_album_pattern = '{$U_ALBUM_PATTERN}';
     var photosUploaded_label = "{'%d photos uploaded'|translate}";
     var batch_Label = "{'Manage this set of %d photos'|translate}";
     var albumSummary_label = "{'Album "%s" now contains %d photos'|translate|escape}";
     var upload_file_types = "{$upload_file_types}";
     var file_exts = "{$file_exts}";
     var uploadedPhotos = [];
     var uploadCategory = null;
    </script>
{/block}

{block name="content"}
    {include file='include/add_album.inc.tpl'}

    <div id="photosAddContent">
	<div class="infos" style="display:none"></div>
	<div class="afterUploadActions btn-group d-none ">
	    <form action="{$U_BATCH}" method="post">
		<input type="hidden" id="batch_photos" name="batch">
		<input type="submit" class="btn btn-sm btn-submit" value="">

	    </form>
	    <a class="btn btn-sm btn-success" href="{$F_ACTION}">{'Add another set of photos'|translate}</a>
	</div>

	{if !empty($setup_errors) > 0}
	    <div class="errors">
		<ul>
		    {foreach $setup_errors as $error}
			<li>{$error}</li>
		    {/foreach}
		</ul>
	    </div>
	{else}

	    {if !empty($setup_warnings) > 0}
		<div class="warnings">
		    <ul>
			{foreach $setup_warnings as $warning}
			    <li>{$warning}</li>
			{/foreach}
		    </ul>
		    <div class="hideButton" style="text-align:center"><a href="{$hide_warnings_link}">{'Hide'|translate}</a></div>
		</div>
	    {/if}


	    <form id="uploadForm" enctype="multipart/form-data" method="post" action="{$form_action}">
		<div class="fieldset">
		    <h3>{'Drop into album'|translate}</h3>

		    <div class="row">
			<div class="col">
			    <select data-selectize="categories" data-value="{$selected_category|json_encode|escape:html}" data-default="first" name="category"></select>
			</div>
			<div class="col">
			    ... {'or'|translate}
			</div>
			<div class="col">
			    <button type="button" class="btn btn-sm btn-submit" data-add-album="category" title="{'Create a new album'|translate}">{'Create a new album'|translate}</button>
			</div>
		    </div>
		</div>

		<p class="showFieldset">
		    <a href="#permissions" class="btn btn-success" data-toggle="collapse">{'Manage Permissions'|translate}</a>
		</p>

		<div class="fieldset collapse" id="permissions">
		    <h3>{'Who can see these photos?'|translate}</h3>

		    <select class="custom-select" name="level" size="1">
			{html_options options=$level_options selected=$level_options_selected}
		    </select>
		</div>

		<div class="fieldset selectFiles">
		    <h3>{'Select files'|translate}</h3>

		    <button id="addFiles" class="btn btn-submit"><i class="fa fa-plus-circle"></i> {'Add Photos'|translate}</button>

		    {if isset($original_resize_maxheight)}
			<p class="form-text">{'The picture dimensions will be reduced to %dx%d pixels.'|translate:$original_resize_maxwidth:$original_resize_maxheight}</p>
		    {/if}

		    <p class="form-text">
			{$upload_max_filesize_shorthand}B. {$upload_file_types}. {if isset($max_upload_resolution)}{$max_upload_resolution}Mpx{/if}
			<a class="showInfo" title="{'Learn more'|translate}"><i class="fa fa-info-circle"></i></a>
		    </p>

		    <p>
			{'Maximum file size: {size}B.'|translate:['size' => $upload_max_filesize_shorthand]}
			{'Allowed file types: {types}.'|translate:['types' => $upload_file_types]}
			{if isset($max_upload_resolution)}
			    {'Approximate maximum resolution: %dM pixels (that\'s %dx%d pixels).'|translate:$max_upload_resolution:$max_upload_width:$max_upload_height}
			{/if}
		    </p>

		    <div id="uploader">
			<p>Your browser doesn't have HTML5 support.</p>
		    </div>

		</div>

		<div id="uploadingActions" style="display:none">
		    <button id="cancelUpload" class="btn btn-cancel"><i class="fa fa-times-circle"></i> {'Cancel'|translate}</button>

		    <div class="big-progressbar">
			<div class="progressbar" style="width:0%"></div>
		    </div>
		</div>

		<button id="startUpload" class="btn btn-success" disabled><i class="fa fa-upload"></i> {'Start Upload'|translate}</button>
	    </form>

	    <div class="fieldset" style="display:none">
		<h3>{'Uploaded Photos'|translate}</h3>
		<div id="uploadedPhotos"></div>
	    </div>
	{/if} {* $setup_errors *}
    </div> <!-- photosAddContent -->
{/block}
