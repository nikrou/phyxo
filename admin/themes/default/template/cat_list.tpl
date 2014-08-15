{combine_script id="cat_list" require="jquery.ui.sortable" load="footer" path="admin/themes/default/js/cat_list.js"}

<h2><span style="letter-spacing:0">{$CATEGORIES_NAV}</span> &#8250; {'Album list management'|translate}</h2>
<p class="showCreateAlbum" id="notManualOrder">
  <a href="#" id="addAlbumOpen">{'create a new album'|translate}</a>
  {if count($categories)}| <a href="#" id="autoOrderOpen">{'apply automatic sort order'|translate}</a>{/if}
  {if ($PARENT_EDIT)}| <a href="{$PARENT_EDIT}"></span>{'edit'|translate}</a>{/if}
</p>
<form id="formCreateAlbum" action="{$F_ACTION}" method="post" style="display:none;">
  <fieldset>
      <legend>{'create a new album'|translate}</legend>
      <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

      <p>
        <strong>{'Album name'|translate}</strong><br>
        <input type="text" name="virtual_name" maxlength="255">
      </p>

      <p class="actionButtons">
        <input class="submit" name="submitAdd" type="submit" value="{'Create'|translate}">
        <a href="#" id="addAlbumClose">{'Cancel'|translate}</a>
      </p>
  </fieldset>
</form>
{if count($categories)}
<form id="formAutoOrder" action="{$F_ACTION}" method="post" style="display:none;">
  <fieldset>
    <legend>{'Automatic sort order'|translate}</legend>
    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

    <p><strong>{'Sort order'|translate}</strong>
  {foreach from=$sort_orders key=sort_code item=sort_label}
      <br><label><input type="radio" value="{$sort_code}" name="order_by" {if $sort_code eq $sort_order_checked}checked="checked"{/if}> {$sort_label}</label>
  {/foreach}
    </p>

    <p>
      <label><input type="checkbox" name="recursive"> <strong>{'Apply to sub-albums'|translate}</strong></label>
    </p>

    <p class="actionButtons">
      <input class="submit" name="submitAutoOrder" type="submit" value="{'Save order'|translate}">
      <a href="#" id="autoOrderClose">{'Cancel'|translate}</a>
    </p>
  </fieldset>
</form>
{/if}

<form id="categoryOrdering" action="{$F_ACTION}" method="post">
  <p id="manualOrder" style="display:none">
    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
    <input class="submit" name="submitManualOrder" type="submit" value="{'Save manual order'|translate}">
    {'... or '|translate} <a href="#" id="cancelManualOrder">{'cancel manual order'|translate}</a>
  </p>

{if count($categories)}
  <ul class="categoryUl">
    {foreach from=$categories item=category}
    <li class="categoryLi{if $category.IS_VIRTUAL} virtual_cat{/if}" id="cat_{$category.ID}">
      <!-- category {$category.ID} -->
      <p class="albumTitle">
        <img src="{$themeconf.admin_icon_dir}/cat_move.png" class="drag_button" style="display:none;" alt="{'Drag to re-order'|translate}" title="{'Drag to re-order'|translate}">
        <strong><a href="{$category.U_CHILDREN}" title="{'manage sub-albums'|translate}">{$category.NAME}</a></strong>
        <span class="albumInfos"><span class="userSeparator">&middot;</span> {$category.NB_PHOTOS|translate_dec:'%d photo':'%d photos'} <span class="userSeparator">&middot;</span> {$category.NB_SUB_PHOTOS|translate_dec:'%d photo':'%d photos'} {$category.NB_SUB_ALBUMS|translate_dec:'in %d sub-album':'in %d sub-albums'}</span>
      </p>

      <input type="hidden" name="catOrd[{$category.ID}]" value="{$category.RANK}">

      <p class="albumActions">
        <a href="{$category.U_EDIT}"><span class="icon-pencil"></span>{'Edit'|translate}</a>
        | <a href="{$category.U_CHILDREN}"><span class="icon-sitemap"></span>{'manage sub-albums'|translate}</a>
        {if isset($category.U_SYNC) }
        | <a href="{$category.U_SYNC}"><span class="icon-exchange"></span>{'Synchronize'|translate}</a>
        {/if}
        {if isset($category.U_DELETE) }
        | <a href="{$category.U_DELETE}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');"><span class="icon-trash"></span>{'delete album'|translate}</a>
      {/if}
      {if cat_admin_access($category.ID)}
        | <a href="{$category.U_JUMPTO}">{'jump to album'|translate} →</a>
      {/if}
      </p>

    </li>
    {/foreach}
  </ul>
{/if}
</form>
