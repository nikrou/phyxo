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
  </fieldset>
  <fieldset>
    <legend>{'Who can delete related tags?'|translate}</legend>
    <p class="field">
      <select name="permission_delete">
	{html_options options=$STATUS_OPTIONS selected=$PERMISSIONS.delete}
      </select>
    </p>
    <p>{'Be careful, whatever the configuration value is, new tag can be deleted anyway'|translate}.</p>
  </fieldset>
  <p>
    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
    <input class="submit" type="submit" name="submit" value="{'Submit'|translate}">
  </p>
</form>
