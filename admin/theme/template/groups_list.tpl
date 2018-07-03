{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Groups'|translate}</a></li>
    <li class="breadcrumb-item">{'Group management'|translate}</li>
{/block}

{block name="content"}
    {combine_script id="group_list" load="footer" path="admin/theme/js/group_list.js"}

    <p><a class="btn btn-submit" data-toggle="collapse" href="#add-group"><i class="fa fa-plus-circle"></i> {'Add group'|translate}</a></p>

	<form method="post" action="{$F_ADD_ACTION}">
		<div class="fieldset collapse" id="add-group">
			<h3>{'Add group'|translate}</h3>

			<p>
			<label for="groupname">{'Group name'|translate}</label>
			<input class="form-control" type="text" id="groupname" name="groupname" maxlength="50" size="20">
			</p>

			<p>
			<input class="btn btn-submit" name="submit_add" type="submit" value="{'Add'|translate}">
			<a class="btn btn-cancel" href="#add-group" data-toggle="collapse">{'Cancel'|translate}</a>
			<input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
			</p>
		</div>
	</form>

    <form method="post" name="add_user" action="{$F_ADD_ACTION}">
	<input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	<table class="table table-hover table-striped">
	    <thead>
		<tr>
		    <th></th>
		    <th>{'Group name'|translate}</th>
		    <th>{'Members'|translate}</th>
		</tr>
	    </thead>
	    <tbody>
		{if not empty($groups)}
		    {foreach $groups as $group}
			<tr>
			    <td><input class="group_selection" name="group_selection[]" type="checkbox" value="{$group.ID}"></td>
			    <td>{$group.NAME}<i><small>{$group.IS_DEFAULT}</small></i></td>
			    <td>
				{if $group.MEMBERS>0}{$group.MEMBERS}<br>{$group.L_MEMBERS}{else}{$group.MEMBERS}{/if}</p>
				<a class="group_perm" href="{$group.U_PERM}" title="{'Permissions'|translate}"><i class="fa fa-lock"></i> {'Permissions'|translate}</a>
			    </td>
		    {/foreach}
		{/if}
	    </tbody>
	</table>

	<div class="fieldset">
	    <h3>{'Action'|translate}</h3>
	    <div id="forbidAction">{'No group selected, no action possible.'|translate}</div>
	    <div id="permitAction" style="display:none">

		<select class="custom-select" name="selectAction">
		    <option value="-1">{'Choose an action'|translate}</option>
		    <option disabled="disabled">------------------</option>
		    <option value="rename">{'Rename'|translate}</option>
		    <option value="delete">{'Delete'|translate}</option>
		    <option value="merge">{'Merge selected groups'|translate}</option>
		    <option value="duplicate">{'Duplicate'|translate}</option>
		    <option value="toggle_default">{'Toggle \'default group\' property'|translate}</option>
		    {if !empty($element_set_groupe_plugins_actions)}
			{foreach from=$element_set_groupe_plugins_actions item=action}
			    <option value="{$action.ID}">{$action.NAME}</option>
			{/foreach}
		    {/if}
		</select>

		<!-- rename -->
		<div id="action_rename" class="bulkAction">
		    {if not empty($groups)}
			{foreach $groups as $group}
			    <p group_id="{$group.ID}" class="grp_action">
				<input class="form-control" type="text" class="large" name="rename_{$group.ID}" value="{$group.NAME}" onfocus="this.value=(this.value=='{$group.NAME}') ? '' : this.value;" onblur="this.value=(this.value=='') ? '{$group.NAME}' : this.value;">
			    </p>
			{/foreach}
		    {/if}
		</div>

		<!-- merge -->
		<div id="action_merge" class="bulkAction">
		    <p id="two_to_select">{'Please select at least two groups'|translate}</p>
		    {assign var='mergeDefaultValue' value='Type here the name of the new group'|translate}
		    <p id="two_atleast">
			<input class="form-control" type="text" name="merge" value="{$mergeDefaultValue}" onfocus="this.value=(this.value=='{$mergeDefaultValue}') ? '' : this.value;" onblur="this.value=(this.value=='') ? '{$mergeDefaultValue}' : this.value;">
		    </p>
		</div>

		<!-- delete -->
		<div id="action_delete" class="bulkAction">
		    <p><label><input type="checkbox" name="confirm_deletion" value="1"> {'Are you sure?'|translate}</label></p>
		</div>

		<!-- duplicate -->
		<div id="action_duplicate" class="bulkAction">
		    {assign var='duplicateDefaultValue' value='Type here the name of the new group'|translate}
		    {if not empty($groups)}
			{foreach $groups as $group}
			    <p group_id="{$group.ID}" class="grp_action">
				{$group.NAME} > <input class="form-control" type="text" class="large" name="duplicate_{$group.ID}" value="{$duplicateDefaultValue}" onfocus="this.value=(this.value=='{$duplicateDefaultValue}') ? '' : this.value;" onblur="this.value=(this.value=='') ? '{$duplicateDefaultValue}' : this.value;">
			    </p>
			{/foreach}
		    {/if}
		</div>

		<!-- toggle_default -->
		<div id="action_toggle_default" class="bulkAction">
		    {if not empty($groups)}
			{foreach $groups as $group}
			    <p group_id="{$group.ID}" class="grp_action">
				{$group.NAME} > {if empty($group.IS_DEFAULT)}{'This group will be set to default'|translate}{else}{'This group will be unset to default'|translate}{/if}
			    </p>
			{/foreach}
		    {/if}
		</div>


		<!-- plugins -->
		{if !empty($element_set_groupe_plugins_actions)}
		    {foreach $element_set_groupe_plugins_actions as $action}
			<div id="action_{$action.ID}" class="bulkAction">
			    {if !empty($action.CONTENT)}{$action.CONTENT}{/if}
			</div>
		    {/foreach}
		{/if}

		<p id="applyActionBlock" style="display:none" class="actionButtons">
		    <input id="applyAction" class="btn btn-submit" type="submit" value="{'Apply action'|translate}" name="submit"> <span id="applyOnDetails"></span></p>
	    </div> <!-- #permitAction -->
	</div>
    </form>
{/block}
