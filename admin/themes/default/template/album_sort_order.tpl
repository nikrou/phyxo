{combine_script id="element_set_ranks" require="jquery.ui.sortable" load="footer" path="admin/themes/default/js/element_set_ranks.js"}

<div class="titrePage">
  <h2><span style="letter-spacing:0">{$CATEGORIES_NAV}</span> &#8250; {'Edit album'|translate} {$TABSHEET_TITLE}</h2>
</div>

<form action="{$F_ACTION}" method="post">
{if !empty($thumbnails)}
  <p><input type="submit" value="{'Submit'|translate}" name="submit"></p>
  <fieldset>
    <legend>{'Manual order'|translate}</legend>
    {if !empty($thumbnails)}
    <p>{'Drag to re-order'|translate}</p>
    <ul class="thumbnails">
      {foreach from=$thumbnails item=thumbnail}
      <li class="rank-of-image">
        <img src="{$thumbnail.TN_SRC}" class="thumbnail" alt="{$thumbnail.NAME|@replace:'"':' '}" title="{$thumbnail.NAME|@replace:'"':' '}"  style="width:{$thumbnail.SIZE[0]}px; height:{$thumbnail.SIZE[1]}px; ">
        <input type="text" name="rank_of_image[{$thumbnail.ID}]" value="{$thumbnail.RANK}" style="display:none">
      </li>
      {/foreach}
    </ul>
    {/if}
  </fieldset>
{/if}

  <fieldset>
    <legend>{'Sort order'|translate}</legend>
    <p class="field">
      <input type="radio" name="image_order_choice" id="image_order_default" value="default"{if $image_order_choice=='default'} checked="checked"{/if}>
      <label for="image_order_default">{'Use the default photo sort order'|translate}</label>
    </p>
    <p class="field">
      <input type="radio" name="image_order_choice" id="image_order_rank" value="rank"{if $image_order_choice=='rank'} checked="checked"{/if}>
      <label for="image_order_rank">{'manual order'|translate}</label>
    </p>
    <p class="field">
      <input type="radio" name="image_order_choice" id="image_order_user_define" value="user_define"{if $image_order_choice=='user_define'} checked="checked"{/if}>
      <label for="image_order_user_define">{'automatic order'|translate}</label>
      <div id="image_order_user_define_options">
      {foreach from=$image_order item=order}
      <p class="field">
        <select name="image_order[]">
          {html_options options=$image_order_options selected=$order}
        </select>
      </p>
      {/foreach}
      </div>
  </fieldset>
  <p>
    <input type="submit" value="{'Submit'|translate}" name="submit">

    <label>
      <input type="checkbox" name="image_order_subcats" id="image_order_subcats">
      {'Apply to sub-albums'|translate}
    </label>
  </p>
</form>
