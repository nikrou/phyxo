{html_head} 
<link rel="stylesheet" type="text/css" href="{$T4U_CSS}/admin.css">
{/html_head}

<div class="titrePage">
  <h2>{'User Tags'|@translate}</h2>
</div>

<p>
{'That plugin allow visitors to add tags to image.'|@translate}
{'You can choose which users (per status) can add and delete tags.'|@translate}
</p>

<form method="post" action="" class="general">
<fieldset>
  <legend>{'Who can add tags?'|@translate}</legend>
  <p class="field">
    <select name="permission_add">
      {html_options options=$STATUS_OPTIONS selected=$T4U_PERMISSION_ADD}
    </select>
  </p>
  <p class="field">  
    <label><input type="checkbox" value="1" name="existing_tags_only" {if ($T4U_EXISTING_TAG_ONLY)}checked="checked"{/if}>{'Only add existing tags'|@translate}</label>
  </p>
</fieldset>
<fieldset>
  <legend>{'Who can delete related tags?'|@translate}</legend>
  <p class="field">
    <select name="permission_delete">
      {html_options options=$STATUS_OPTIONS selected=$T4U_PERMISSION_DELETE}
    </select>
  </p>
  <p>{'Be careful, whatever the configuration value is, new tag can be deleted anyway'|@translate}.</p>
</fieldset>
<p><input class="submit" type="submit" name="submit" value="{'Submit'|@translate}"></p>
</form>

