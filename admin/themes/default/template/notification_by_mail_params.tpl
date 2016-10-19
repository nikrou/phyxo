{include file='include/autosize.inc.tpl'}

<div class="titrePage">
  <h2>{'Send mail to users'|translate} {$TABSHEET_TITLE}</h2>
</div>

<form method="post" name="notification_by_mail" id="notification_by_mail" action="{$F_ACTION}">
  {if isset($REPOST_SUBMIT_NAME)}
  <fieldset>
    <div class="infos">
      <input type="submit" value="{'Continue processing treatment'|translate}" name="{$REPOST_SUBMIT_NAME}">
    </div>
  </fieldset>
  {/if}

  <fieldset>
    <legend>{'Parameters'|translate}</legend>
    <table>
      <tr>
        <td><label>{'Send mail on HTML format'|translate}</label></td>
        <td>
          <label><input type="radio" name="nbm_send_html_mail" value="true"  {if $SEND_HTML_MAIL}checked="checked"{/if}>{'Yes'|translate}</label>
          <label><input type="radio" name="nbm_send_html_mail" value="false" {if not $SEND_HTML_MAIL}checked="checked"{/if}>{'No'|translate}</label>
        </td>
      </tr>
      <tr>
        <td>
          <label for="send_mail_as">{'Send mail as'|translate}</label>
          <br><i><small>{'With blank value, gallery title will be used'|translate}</small></i>
        </td>
        <td><input type="text" maxlength="35" size="35" name="nbm_send_mail_as" id="send_mail_as" value="{$SEND_MAIL_AS}"></td>
      </tr>
      <tr>
        <td><label>{'Add detailed content'|translate}</label></td>
        <td>
          <label><input type="radio" name="nbm_send_detailed_content" value="true"  {if $SEND_DETAILED_CONTENT}checked="checked"{/if}>{'Yes'|translate}</label>
          <label><input type="radio" name="nbm_send_detailed_content" value="false" {if not $SEND_DETAILED_CONTENT}checked="checked"{/if}>{'No'|translate}</label>
        </td>
      </tr>
     <tr>
        <td><label for="complementary_mail_content">{'Complementary mail content'|translate}</label></td>
        <td><textarea cols="50" rows="5" name="nbm_complementary_mail_content" id="complementary_mail_content">{$COMPLEMENTARY_MAIL_CONTENT}</textarea></td>
      </tr>
      <tr>
        <td>
          <label>{'Include display of recent photos grouped by dates'|translate}</label>
          <br><i><small>{'Available only with HTML format'|translate}</small></i>
        </td>
        <td>
          <label><input type="radio" name="nbm_send_recent_post_dates" value="true" {if $SEND_RECENT_POST_DATES}checked="checked"{/if}>{'Yes'|translate}</label>
          <label><input type="radio" name="nbm_send_recent_post_dates" value="false" {if not $SEND_RECENT_POST_DATES}checked="checked"{/if}>{'No'|translate}</label>
        </td>
      </tr>
    </table>
  </fieldset>

  <p>
    <input type="submit" value="{'Submit'|translate}" name="param_submit">
    <input type="reset" value="{'Reset'|translate}" name="param_reset">
  </p>
</form>
