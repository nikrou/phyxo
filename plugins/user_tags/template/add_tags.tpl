{combine_css path="$T4U_CSS/style.css"}
{combine_script id="jquery.tokeninput" require="jquery" path="themes/default/js/plugins/jquery.tokeninput.js"}
{combine_script id="addtags" require="jquery" path="$T4U_JS/jquery.addtags.js"}
{footer_script require="jquery.tokeninput,addtags"}

var t4u_list_script = '{$T4U_LIST_SCRIPT}';
var vocab = [];
vocab['click_to_add_tags'] = "{'Click to add tags'|@translate}";
vocab['start_to_type'] = "{'Start to type'|@translate}...";
vocab['no_results'] = "{'No results'|@translate}",
vocab['searching_text'] = "{'Searching...'|@translate}",
vocab['new_text'] = " ({'new'|@translate})";
var t4u_allow_creation = {$T4U_ALLOW_CREATION};
{/footer_script}

<form style="display:none" name="t4u-update-tags" id="t4u-update-tags" action="{$T4U_UPDATE_SCRIPT}" method="post">
  <input type="hidden" id="t4u-image-id" name="image_id" value="{$T4U_IMAGE_ID}">
  <input type="hidden" id="t4u-referer" name="referer" value="{$T4U_REFERER}">

  <select id="t4u-tags" name="tags">
    {foreach from=$T4U_RELATED_TAGS item=tag key=id}
    <option value="{$id}">{$tag}</option>
    {/foreach}
  </select>
  <input id="t4u-update" type="submit" disabled="disabled" class="t4u-disabled" value="{'Update tags'|@translate}">
</form>
