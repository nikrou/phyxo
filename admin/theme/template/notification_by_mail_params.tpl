{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Notification'|translate}</a></li>
    <li class="breadcrumb-item">{'Parameters'|translate}</li>
{/block}

{block name="content"}
    <form method="post" name="notification_by_mail" id="notification_by_mail" action="{$F_ACTION}">
	{if isset($REPOST_SUBMIT_NAME)}
	    <div class="fieldset">
		<input type="btn btn-submit" value="{'Continue processing treatment'|translate}" name="{$REPOST_SUBMIT_NAME}">
	    </div>
	{/if}

	<div class="fieldset">
	    <h3>{'Parameters'|translate}</h3>
	    <p>
		<h4>{'Send mail on HTML format'|translate}</h4>

		<label><input type="radio" name="nbm_send_html_mail" value="true"  {if $SEND_HTML_MAIL}checked="checked"{/if}>{'Yes'|translate}</label>
		<label><input type="radio" name="nbm_send_html_mail" value="false" {if not $SEND_HTML_MAIL}checked="checked"{/if}>{'No'|translate}</label>
	    </p>

	    <p>
		<label for="send_mail_as">{'Send mail as'|translate}</label>
		<i><small>{'With blank value, gallery title will be used'|translate}</small></i>
		    </td>
		    <input class="form-control" type="text" maxlength="35" size="35" name="nbm_send_mail_as" id="send_mail_as" value="{$SEND_MAIL_AS}">
	    </p>

	    <p>
		<h4>{'Add detailed content'|translate}</h4>

		<label><input type="radio" name="nbm_send_detailed_content" value="true"  {if $SEND_DETAILED_CONTENT}checked="checked"{/if}>{'Yes'|translate}</label>
		<label><input type="radio" name="nbm_send_detailed_content" value="false" {if not $SEND_DETAILED_CONTENT}checked="checked"{/if}>{'No'|translate}</label>
	    </p>

	    <p
		<label for="complementary_mail_content">{'Complementary mail content'|translate}</label>
		<textarea class="form-control" cols="50" rows="5" name="nbm_complementary_mail_content" id="complementary_mail_content">{$COMPLEMENTARY_MAIL_CONTENT}</textarea>
	    </p>

	    <p>
		<h4>{'Include display of recent photos grouped by dates'|translate}</h4>
		<i><small>({'Available only with HTML format'|translate})</small></i>
		<label><input type="radio" name="nbm_send_recent_post_dates" value="true" {if $SEND_RECENT_POST_DATES}checked="checked"{/if}>{'Yes'|translate}</label>
		<label><input type="radio" name="nbm_send_recent_post_dates" value="false" {if not $SEND_RECENT_POST_DATES}checked="checked"{/if}>{'No'|translate}</label>
	    </p>
	</div>

	<p>
	    <input class="btn btn-submit" type="submit" value="{'Submit'|translate}" name="param_submit">
	    <input class="btn btn-reset" type="reset" value="{'Reset'|translate}" name="param_reset">
	</p>
    </form>
{/block}
