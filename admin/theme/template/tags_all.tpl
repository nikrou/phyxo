{extends file="__layout.tpl"}

{block name="content"}
    {html_style}
    .showInfo { text-indent:5px; }
    {/html_style}
    {html_head}
    <script type="text/javascript">
     var phyxo_msg = phyxo_msg || {};
     phyxo_msg.select_at_least_two_tags = "{'Select at least two tags for merging'|translate}";
    </script>
    {/html_head}
    {combine_script id="tags" load="footer" path="admin/theme/js/tags.js"}

    <div class="titrePage">
	<h2>{'Manage tags'|translate}</h2>
    </div>

    <form action="{$F_ACTION}" method="post">
	{if isset($EDIT_TAGS_LIST)}
	    <fieldset>
		<legend>{'Edit tags'|translate}</legend>
		<input type="hidden" name="edit_list" value="{$EDIT_TAGS_LIST}">
		<table class="table2">
		    <tr class="throw">
			<th>{'Current name'|translate}</th>
			<th>{'New name'|translate}</th>
		    </tr>
		    {foreach from=$tags item=tag}
			<tr>
			    <td>{$tag.NAME}</td>
			    <td><input type="text" name="tag_name-{$tag.ID}" value="{$tag.NAME}" size="50"></td>
			</tr>
		    {/foreach}
		</table>

		<p>
		    <input type="submit" name="edit_submit" value="{'Submit'|translate}">
		    <input type="submit" name="edit_cancel" value="{'Cancel'|translate}">
		</p>
	    </fieldset>
	{/if}

	{if isset($DUPLIC_TAGS_LIST)}
	    <fieldset>
		<legend>{'Edit tags'|translate}</legend>
		<input type="hidden" name="edit_list" value="{$DUPLIC_TAGS_LIST}">
		<table class="table2">
		    <tr class="throw">
			<th>{'Source tag'|translate}</th>
			<th>{'Name of the duplicate'|translate}</th>
		    </tr>
		    {foreach from=$tags item=tag}
			<tr>
			    <td>{$tag.NAME}</td>
			    <td><input type="text" name="tag_name-{$tag.ID}" value="{$tag.NAME}" size="50"></td>
			</tr>
		    {/foreach}
		</table>

		<p>
		    <input type="submit" name="duplic_submit" value="{'Submit'|translate}">
		    <input type="submit" name="duplic_cancel" value="{'Cancel'|translate}">
		</p>
	    </fieldset>
	{/if}

	{if isset($MERGE_TAGS_LIST)}
	    <fieldset id="mergeTags">
		<legend>{'Merge tags'|translate}</legend>
		{'Select the destination tag'|translate}

		<p>
		    {foreach from=$tags item=tag name=tagloop}
			<label><input type="radio" name="destination_tag" value="{$tag.ID}"{if $smarty.foreach.tagloop.index == 0} checked="checked"{/if}> {$tag.NAME}<span class="warningDeletion"> {'(this tag will be deleted)'|translate}</span></label><br>
		    {/foreach}
		</p>

		<p>
		    <input type="hidden" name="merge_list" value="{$MERGE_TAGS_LIST}">
		    <input type="submit" name="merge_submit" value="{'Confirm merge'|translate}">
		    <input type="submit" name="merge_cancel" value="{'Cancel'|translate}">
		</p>
	    </fieldset>
	{/if}

	<fieldset>
	    <legend>{'Add a tag'|translate}</legend>

	    <label>
		{'New tag'|translate}
		<input type="text" name="add_tag" size="50">
	    </label>

	    <p><input class="submit" type="submit" name="add" value="{'Submit'|translate}"></p>
	</fieldset>

	<fieldset>
	    <legend>{'Tag selection'|translate}</legend>

	    {if count($all_tags)}
		<div><label><i class="fa fa-filter visibility-hidden" id="filterIcon"></i>{'Search'|translate}: <input id="searchInput" type="text" size="12"></label></div>
	    {/if}

	    <ul class="tagSelection">
		{foreach from=$all_tags item=tag}
		    <li>
			{capture name='showInfo'}{strip}
			<b>{$tag.name}</b> ({$tag.counter|translate_dec:'%d photo':'%d photos'})<br>
			<a href="{$tag.U_VIEW}">{'View in gallery'|translate}</a> |
			<a href="{$tag.U_EDIT}">{'Manage photos'|translate}</a>
			{if !empty($tag.alt_names)}<br>{$tag.alt_names}{/if}
        {/strip}{/capture}
        <a class="showInfo" title="{$smarty.capture.showInfo|@htmlspecialchars}"><i class="fa fa-info-circle"></i></a>
        <label>
            <input type="checkbox" name="tags[]" value="{$tag.id}"> {$tag.name}
        </label>
		    </li>
		{/foreach}
	    </ul>

	    <p>
		<input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
		<input type="submit" name="edit" value="{'Edit selected tags'|translate}">
		<input type="submit" name="duplicate" value="{'Duplicate selected tags'|translate}">
		<input type="submit" name="merge" value="{'Merge selected tags'|translate}">
		<input type="submit" name="delete" value="{'Delete selected tags'|translate}" onclick="return confirm('{'Are you sure?'|translate}');">
	    </p>
	</fieldset>

    </form>
{/block}
