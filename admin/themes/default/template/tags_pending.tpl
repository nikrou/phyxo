{combine_script id="pending-tags" load="footer" path="admin/themes/default/js/phyxo.js"}
{footer_script}
$(function() {
phyxo.checkboxesHelper('#pending-tags');
});
{/footer_script}
<div class="titrePage">
  <h2>{'Pendings tags'|translate}</h2>
</div>

<form action="{$F_ACTION}" method="post" id="pending-tags">
  <table class="table2 checkboxes">
    <thead>
      <tr>
	<th>&nbsp;</th>
	<th>{'Name'|translate}</th>
	<th>{'Image'|translate}</th>
	<th>{'Created by'|translate}</th>
	<th>{'Status'|translate}</th>
      </tr>
    </thead>
    <tbody>
      {foreach $tags as $tag}
      <tr{if $tag@index%2==0} class="even"{/if}>
	<td class="check">{$tag.id} <input type="checkbox" name="tag_ids[{$tag.image_id}][]" value="{$tag.id}"/></td>
	<td>{$tag.name}</td>
	<td><a href="{$tag.picture_url}"><img src="{$tag.thumb_src}"></a></td>
	<td>{if $tag.created_by}{$tag.username}{/if}</td>
	<td class="{if $tag.status==1}added{else}deleted{/if}">{$tag.status}</td>
      </tr>
      {/foreach}
    </tbody>
  </table>

  <p class="check-actions">
    {'Select:'|translate}
    <a href="#" class="select all">{'All'|translate}</a>,
    <a href="#" class="select none">{'None'|translate}</a>,
    <a href="#" class="select invert">{'Invert'|translate}</a>
  </p>

  <p class="formButtons">
    <input type="submit" name="validate" value="{'Validate'|translate}">
    <input type="submit" name="reject" value="{'Reject'|translate}">
    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
  </p>
</form>
