<!-- DEV TAG: not smarty migrated -->
<!-- $Id$ -->
<div class="titrePage">
  <ul class="categoryActions">
    <li><a href="{U_HELP}" onclick="popuphelp(this.href); return false;" title="{lang:Help}"><img src="{themeconf:icon_dir}/help.png" class="button" alt="(?)"></a></li>
  </ul>
  <h2>{lang:title_groups}</h2>
</div>

<form method="post" name="add_user" action="{F_ADD_ACTION}" class="properties">
  <fieldset>
    <legend>{lang:Add group}</legend>

    <span class="property">
      <label for="groupname">{lang:Group name}</label>
    </span>
    <input type="text" id="groupname" name="groupname" maxlength="50" size="20" />

    <p>
      <input class="submit" type="submit" name="submit_add" value="{lang:Add}" {TAG_INPUT_ENABLED}/>
    </p>
  </fieldset>
</form>

<table class="table2">
  <tr class="throw">
    <th>{lang:Group name}</th>
    <th>{lang:Members}</th>
    <th>{lang:Actions}</th>
  </tr>
  <!-- BEGIN group -->
  <tr class="{group.CLASS}">
    <td>{group.NAME}<i><small>{group.IS_DEFAULT}</small></i></td>
    <td><a href="{group.U_MEMBERS}">{group.MEMBERS}</a></td>
    <td style="text-align:center;">
      <a href="{group.U_PERM}">
        <img src="{themeconf:icon_dir}/permissions.png" class="button" style="border:none" id="btn_permissions" alt="{lang:permissions}" title="{lang:permissions}" /></a>
      <a href="{group.U_DELETE}" onclick="return confirm('{lang:Action: }' + document.getElementById('btn_delete').title + '\n\n' + '{lang:Are you sure?}');">
        <img src="{themeconf:icon_dir}/delete.png" class="button" style="border:none" id="btn_delete" alt="{lang:delete}" title="{lang:delete}" {TAG_INPUT_ENABLED}/></a>
      <a href="{group.U_ISDEFAULT}" onclick="return confirm('{lang:Action: }' + document.getElementById('btn_toggle_is_default_group').title + '\n\n' + '{lang:Are you sure?}');">
        <img src="{themeconf:icon_dir}/toggle_is_default_group.png" class="button" style="border:none" id="btn_toggle_is_default_group" alt="{lang:toggle_is_default_group}" title="{lang:toggle_is_default_group}" {TAG_INPUT_ENABLED}/></a>
    </td>
  </tr>
  <!-- END group -->
</table>
