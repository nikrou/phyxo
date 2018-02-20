{extends file="__layout.tpl"}

{block name="content"}
    <form method="post" action="" class="general">
	<fieldset>
	    <legend>{'Who can add tags?'|translate}</legend>

	    <p class="field">
		<select name="permission_add">
		    {html_options options=$STATUS_OPTIONS selected=$PERMISSIONS.add}
		</select>
	    </p>
	    <p class="field">
		<label>
		    <input type="checkbox" value="1" name="existing_tags_only" {if ($PERMISSIONS.existing_tags_only)}checked="checked"{/if}>
		    &nbsp;{'Only add existing tags'|translate}
		</label>
	    </p>
	    <p class="field">
		<label>
		    <input type="checkbox" value="1" name="publish_tags_immediately" {if (!$PERMISSIONS.publish_tags_immediately)}checked="checked"{/if}>
		    &nbsp;{'Moderate added tags'|translate}
		</label>
	    </p>
	</fieldset>
	<fieldset>
	    <legend>{'Who can delete related tags?'|translate}</legend>
	    <p class="field">
		<select name="permission_delete">
		    {html_options options=$STATUS_OPTIONS selected=$PERMISSIONS.delete}
		</select>
	    </p>
	    <p>{'Be careful, whatever the configuration value is, new tag can be deleted anyway'|translate}.</p>
	    <p class="field">
		<label>
		    <input type="checkbox" value="1" name="delete_tags_immediately" {if (!$PERMISSIONS.delete_tags_immediately)}checked="checked"{/if}>
		    &nbsp;{'Moderate deleted tags'|translate}
		</label>
	    </p>
	    <p>
		{'If a user delete a tag and you "moderate delete tags", then theses tags will be displayed to all users until you validate the deletion.'|translate}
	    </p>
	</fieldset>
	<fieldset>
	    <legend>{'Display for pending tags'|translate}</legend>
	    <p>
		{'By default, if you allow some users to add tags, theses tags are not shown to them (nor others users). And pending deleted tags are shown.'|translate}
	    </p>
	    <p class="field">
		<label>
		    <input type="checkbox" value="1" name="show_pending_added_tags" {if ($PERMISSIONS.show_pending_added_tags)}checked="checked"{/if}>
		    &nbsp;{'Show added pending tags to the user who add them'|translate}
		</label>
	    </p>
	    <p>
		{'A css class is added to tag to show deleted pending tags differently to the user who delete them'|translate}
	    </p>
	</fieldset>
	<p>
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	    <input class="submit" type="submit" name="submit" value="{'Submit'|translate}">
	</p>
    </form>
{/block}
