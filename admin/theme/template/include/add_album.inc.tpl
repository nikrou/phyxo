{if empty($load_mode)}{$load_mode="footer"}{/if}
{include file="include/colorbox.inc.tpl" load_mode=$load_mode}

{combine_script id="jquery.selectize" load="footer" path="admin/theme/js/plugins/selectize.js"}
{combine_css id="jquery.selectize" path="admin/theme/js/plugins/selectize.clear.css"}

{combine_script id="addAlbum" load=$load_mode path="admin/theme/js/addAlbum.js"}

<div style="display:none">
  <div id="addAlbumForm">
    <form action="">
      <p>
	<label>{'Parent album'|translate}</label>
	<select name="category_parent"><option></option></select>
      </p>

      <p>
	<label>{'Album name'|translate}</label>
	<input name="category_name" type="text" maxlength="255">
	<span id="categoryNameError"></span>
      </p>

      <p>
	<input type="submit" value="{'Create'|translate}">
	<span id="albumCreationLoading" style="display:none"><img src="./theme/images/ajax-loader-small.gif" alt=""></span>
      </p>
    </form>
  </div>
</div>
