{html_head}
<script src="./theme/js/common.js"></script>
<script src="./theme/js/plugins/jquery.jgrowl.js"></script>
<script src="./theme/js/plugins/plupload/moxie.js"></script>
<script src="./theme/js/plugins/plupload/plupload.js"></script>
<script src="./theme/js/plugins/plupload/jquery.plupload.queue/jquery.plupload.queue.js"></script>
<script src="./theme/js/LocalStorageCache.js"></script>
<script src="./theme/js/plugins/selectize.js"></script>
<script src="./theme/js/photos_add_direct.js"></script>
{assign var="plupload_i18n" value="./theme/js/plugins/plupload/i18n/`$lang_info.plupload_code`.js"}
{if "PHPWG_ROOT_PATH"|@constant|@cat:$plupload_i18n|@file_exists}
  <script src="{{$plupload_i18n}}"></script>
{/if}
{/html_head}
{footer_script}
{* <!-- CATEGORIES --> *}
var categoriesCache = new CategoriesCache({
  serverKey: '{$CACHE_KEYS.categories}',
  serverId: '{$CACHE_KEYS._hash}',
  rootUrl: '{$ROOT_URL}'
});

categoriesCache.selectize($('[data-selectize=categories]'), {
  filter: function(categories, options) {
    if (categories.length > 0) {
      jQuery("#albumSelection, .selectFiles, .showFieldset").show();
    }

    return categories;
  }
});

$('[data-add-album]').pwgAddAlbum({ cache: categoriesCache });

var pwg_token = '{$pwg_token}';
var photosUploaded_label = "{'%d photos uploaded'|translate}";
var batch_Label = "{'Manage this set of %d photos'|translate}";
var albumSummary_label = "{'Album "%s" now contains %d photos'|translate|escape}";
var upload_file_types = "{$upload_file_types}";
var uploadedPhotos = [];
var uploadCategory = null;

{/footer_script}

{combine_css path="admin/theme/js/plugins/jquery.jgrowl.css"}
{combine_css path="admin/theme/js/plugins/plupload/jquery.plupload.queue/css/jquery.plupload.queue.css"}
{include file='include/colorbox.inc.tpl'}
{include file='include/add_album.inc.tpl'}

{combine_css id='jquery.selectize' path="admin/theme/js/plugins/selectize.clear.css"}


<div class="titrePage">
  <h2>{'Upload Photos'|translate} {$TABSHEET_TITLE}</h2>
</div>

<div id="photosAddContent">

<div class="infos" style="display:none"></div>

<p class="afterUploadActions" style="margin:10px; display:none;"><a class="batchLink"></a> | <a href="">{'Add another set of photos'|translate}</a></p>

{if count($setup_errors) > 0}
<div class="errors">
  <ul>
  {foreach from=$setup_errors item=error}
    <li>{$error}</li>
  {/foreach}
  </ul>
</div>
{else}

{if count($setup_warnings) > 0}
<div class="warnings">
  <ul>
    {foreach from=$setup_warnings item=warning}
    <li>{$warning}</li>
    {/foreach}
  </ul>
  <div class="hideButton" style="text-align:center"><a href="{$hide_warnings_link}">{'Hide'|translate}</a></div>
</div>
{/if}


<form id="uploadForm" enctype="multipart/form-data" method="post" action="{$form_action}">
  <fieldset class="selectAlbum">
    <legend>{'Drop into album'|translate}</legend>

    <span id="albumSelection" style="display:none">
      <select data-selectize="categories" data-value="{$selected_category|@json_encode|escape:html}"
              data-default="first" name="category" style="width:600px"></select>
      <br>{'... or '|translate}</span>
    <a href="#" data-add-album="category" title="{'create a new album'|translate}">{'create a new album'|translate}</a>
  </fieldset>

  <p class="showFieldset" style="display:none"><a id="showPermissions" href="#">{'Manage Permissions'|translate}</a></p>

  <fieldset id="permissions" style="display:none">
    <legend>{'Who can see these photos?'|translate}</legend>

    <select name="level" size="1">
      {html_options options=$level_options selected=$level_options_selected}
    </select>
  </fieldset>

    <fieldset class="selectFiles" style="display:none">
      <legend>{'Select files'|translate}</legend>

      <button id="addFiles" class="buttonLike icon-plus-circled">{'Add Photos'|translate}</button>

      {if isset($original_resize_maxheight)}
      <p class="uploadInfo">{'The picture dimensions will be reduced to %dx%d pixels.'|translate:$original_resize_maxwidth:$original_resize_maxheight}</p>
      {/if}

      <p id="uploadWarningsSummary">
	{$upload_max_filesize_shorthand}B. {$upload_file_types}. {if isset($max_upload_resolution)}{$max_upload_resolution}Mpx{/if}
	<a class="icon-info-circled-1 showInfo" title="{'Learn more'|translate}"></a>
      </p>

      <p id="uploadWarnings">
        {'Maximum file size: %sB.'|translate:$upload_max_filesize_shorthand}
        {'Allowed file types: %s.'|translate:$upload_file_types}
	{if isset($max_upload_resolution)}
        {'Approximate maximum resolution: %dM pixels (that\'s %dx%d pixels).'|translate:$max_upload_resolution:$max_upload_width:$max_upload_height}
	{/if}
      </p>

      <div id="uploader">
        <p>Your browser doesn't have HTML5 support.</p>
      </div>

    </fieldset>

    <div id="uploadingActions" style="display:none">
      <button id="cancelUpload" class="buttonLike icon-cancel-circled">{'Cancel'|translate}</button>

      <div class="big-progressbar">
        <div class="progressbar" style="width:0%"></div>
      </div>
    </div>

    <button id="startUpload" class="buttonLike icon-upload" disabled>{'Start Upload'|translate}</button>
</form>

<fieldset style="display:none">
  <legend>{'Uploaded Photos'|translate}</legend>
  <div id="uploadedPhotos"></div>
</fieldset>

{/if} {* $setup_errors *}
</div> <!-- photosAddContent -->