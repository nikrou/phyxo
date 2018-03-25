{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Tags'|translate}</a></li>
    <li class="breadcrumb-item">{'Manage tags'|translate}</li>
{/block}

{block name="content"}
    {html_head}
    <script type="text/javascript">
     var phyxo_msg = phyxo_msg || {};
     phyxo_msg.select_at_least_two_tags = "{'Select at least two tags for merging'|translate}";
    </script>
    {/html_head}
    {combine_script id="tags" load="footer" path="admin/theme/js/tags.js"}

    <form action="{$F_ACTION}" method="post">
	<div class="fieldset">
	    <h3>{'Add a tag'|translate}</h3>

	    <label>
		{'New tag'|translate}
		<input type="text" class="form-control" name="add_tag" size="50">
	    </label>

	    <p><input class="btn btn-submit" type="submit" name="add" value="{'Submit'|translate}"></p>
	</div>

	{if isset($EDIT_TAGS_LIST)}
	    <div class="fieldset">
		<h3>{'Edit tags'|translate}</h3>
		<input type="hidden" name="edit_list" value="{$EDIT_TAGS_LIST}">
		<table class="table">
		    <thead>
			<tr>
			    <th>{'Current name'|translate}</th>
			    <th>{'New name'|translate}</th>
			</tr>
		    </thead>
		    <tbody>
			{foreach $tags as $tag}
			    <tr>
				<td>{$tag.NAME}</td>
				<td><input type="text" class="form-control" name="tag_name-{$tag.ID}" value="{$tag.NAME}" size="50"></td>
			    </tr>
			{/foreach}
		    </tbody>
		</table>

		<p>
		    <input type="submit" class="btn btn-edit" name="edit_submit" value="{'Submit'|translate}">
		    <input type="submit" class="btn btn-cancel" name="edit_cancel" value="{'Cancel'|translate}">
		</p>
	    </div>
	{/if}

	{if isset($DUPLIC_TAGS_LIST)}
	    <div class="fieldset">
		<h3>{'Edit tags'|translate}</h3>
		<input type="hidden" name="edit_list" value="{$DUPLIC_TAGS_LIST}">
		<table class="table">
		    <tr class="throw">
			<th>{'Source tag'|translate}</th>
			<th>{'Name of the duplicate'|translate}</th>
		    </tr>
		    {foreach $tags as $tag}
			<tr>
			    <td>{$tag.NAME}</td>
			    <td><input type="text" class="form-control" name="tag_name-{$tag.ID}" value="{$tag.NAME}" size="50"></td>
			</tr>
		    {/foreach}
		</table>

		<p>
		    <input type="submit" class="btn btn-submit" name="duplic_submit" value="{'Submit'|translate}">
		    <input type="submit" class="btn btn-cancel" name="duplic_cancel" value="{'Cancel'|translate}">
		</p>
	    </div>
	{/if}

	{if isset($MERGE_TAGS_LIST)}
	    <div class="fieldset" id="mergeTags">
		<h3>{'Merge tags'|translate}</h3>
		{'Select the destination tag'|translate}

		<p>
		    {foreach $tags as $tag}
			<label><input type="radio" class="form-control" name="destination_tag" value="{$tag.ID}"{if $tag@index == 0} checked="checked"{/if}> {$tag.NAME}<span class="warningDeletion"> {'(this tag will be deleted)'|translate}</span></label>
		    {/foreach}
		</p>

		<p>
		    <input type="hidden" name="merge_list" value="{$MERGE_TAGS_LIST}">
		    <input type="submit" class="btn btn-submit" name="merge_submit" value="{'Confirm merge'|translate}">
		    <input type="submit" class="btn btn-cancel" name="merge_cancel" value="{'Cancel'|translate}">
		</p>
	    </div>
	{/if}

	<div class="fieldset">
	    <h3>{'Tag selection'|translate}</h3>

	    {if count($all_tags)}
		<p><label><i class="fa fa-filter visibility-hidden" id="filterIcon"></i>{'Search'|translate}: <input id="searchInput" class="form-control" type="text" size="12"></label></p>
	    {/if}

	    {foreach $all_tags as $tag}
		<div class="form-check form-check-inline">
		    {capture name='showInfo'}{strip}
		    <b>{$tag.name}</b> ({$tag.counter|translate_dec:'%d photo':'%d photos'})<br>
		    <a href="{$tag.U_VIEW}">{'View in gallery'|translate}</a> |
		    <a href="{$tag.U_EDIT}">{'Manage photos'|translate}</a>
		    {if !empty($tag.alt_names)}<br>{$tag.alt_names}{/if}
                    {/strip}{/capture}
		    <a class="showInfo" title="{$smarty.capture.showInfo|@htmlspecialchars}"><i class="fa fa-info-circle"></i></a>
		    <input type="checkbox" id="tag_id_{$tag.id}" name="tags[]" value="{$tag.id}">
		    <label for="tag_id_{$tag.id}">{$tag.name}</label>
		</div>
	    {/foreach}

	    <p>
		<input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
		<input type="submit" class="btn btn-edit" name="edit" value="{'Edit selected tags'|translate}">
		<input type="submit" class="btn btn-duplicate" name="duplicate" value="{'Duplicate selected tags'|translate}">
		<input type="submit" class="btn btn-merge" name="merge" value="{'Merge selected tags'|translate}">
		<input type="submit" class="btn btn-delete" name="delete" value="{'Delete selected tags'|translate}" onclick="return confirm('{'Are you sure?'|translate}');">
	    </p>
	</div>
    </form>
{/block}
