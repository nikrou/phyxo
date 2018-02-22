{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Tags'|translate}</a></li>
    <li class="breadcrumb-item">{'Pendings tags'|translate}</li>
{/block}

{block name="content"}
    {combine_script id="pending-tags" load="footer" path="admin/theme/js/phyxo.js"}
    {footer_script}
    $(function() {
    phyxo.checkboxesHelper('#pending-tags');
    });
    {/footer_script}

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
			<td class="check"><input type="checkbox" name="tag_ids[{$tag.image_id}][]" value="{$tag.id}"/></td>
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
{/block}
