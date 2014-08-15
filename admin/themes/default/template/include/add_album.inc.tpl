{if empty($load_mode)}{$load_mode='footer'}{/if}
{include file='include/colorbox.inc.tpl' load_mode=$load_mode}

{assign var="selectizeTheme" value=($themeconf.name=='roma')|ternary:'dark':'default'}
{combine_script id='jquery.selectize' load='footer' path="admin/themes/default/js/plugins/selectize.min.js"}
{combine_css id='jquery.selectize' path="admin/themes/default/js/plugins/selectize.`$selectizeTheme`.css"}

{combine_script id='addAlbum' load=$load_mode path='admin/themes/default/js/addAlbum.js'}

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
	<span id="albumCreationLoading" style="display:none"><img src="admin/themes/default/images/ajax-loader-small.gif" alt=""></span>
      </p>
    </form>
  </div>
</div>
