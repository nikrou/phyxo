{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Notification'|translate}</a></li>
    <li class="breadcrumb-item">{'Send'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     $(function() {
	 $("#checkAllLink").click(function () {
	     $("#notification_by_mail input[type=checkbox]").prop('checked', true);
	     return false;
	 });

	 $("#uncheckAllLink").click(function () {
	     $("#notification_by_mail input[type=checkbox]").prop('checked', false);
	     return false;
	 });
     });
    </script>
{/block}

{block name="content"}
    <form method="post" name="notification_by_mail" id="notification_by_mail" action="{$F_ACTION}">
	{if isset($REPOST_SUBMIT_NAME)}
	    <div class="fieldset">
		<input class="btn btn-submit" type="submit" value="{'Continue processing treatment'|translate}" name="{$REPOST_SUBMIT_NAME}">
	    </div>
	{/if}

	{if isset($subscribe)}
	    <div class="fieldset">
		<h3>{'Subscribe/unsubscribe users'|translate}</h3>
		<p><i>{'Warning: subscribing or unsubscribing will send mails to users'|translate}</i></p>

		{include file="double_select.tpl"}
	    </div>
	{/if}

	{if isset($send)}
	    {if empty($send.users)}
		<p>{'There is no available subscribers to mail.'|translate}</p>
		<p>
		    {'Subscribers could be listed (available) only if there is new elements to notify.'|translate}<br>
		    {'Anyway only webmasters can see this tab and never administrators.'|translate}
		</p>
	    {else}
		<div class="fieldset">
		    <h3>{'Select recipients'|translate}</h3>
		    <table class="table table-hover table-striped">
			<thead>
			    <tr>
				<th>{'User'|translate}</th>
				<th>{'Email'|translate}</th>
				<th>{'Last send'|translate}</th>
				<th>{'To send ?'|translate}</th>
			    </tr>
			</thead>
			<tbody>
			    {foreach $send.users as $u}
				<tr>
				    <td><label for="send_selection-{$u.ID}">{$u.USERNAME}</label></td>
				    <td><label for="send_selection-{$u.ID}">{$u.EMAIL}</label></td>
				    <td><label for="send_selection-{$u.ID}">{$u.LAST_SEND}</label></td>
				    <td><input type="checkbox" name="send_selection[]" value="{$u.ID}" {$u.CHECKED} id="send_selection-{$u.ID}"></td>
				</tr>
			    {/foreach}
			</tbody>
		    </table>
		    <p>
			<a href="#" id="checkAllLink">{'Check all'|translate}</a>
			/ <a href="#" id="uncheckAllLink">{'Uncheck all'|translate}</a>
		    </p>
		</div>

		<div class="fieldset">
		    <h3>{'Options'|translate}</h3>
		    <p>
			<label for="send_customize_mail_content">{'Complementary mail content'|translate}</label>
			<textarea class="form-control" cols="50" rows="5" name="send_customize_mail_content" id="send_customize_mail_content">{$send.CUSTOMIZE_MAIL_CONTENT}</textarea>
		    </p>
		</div>

		<p>
		    <input class="btn btn-submit" type="submit" value="{'Send'|translate}" name="send_submit">
		</p>
	    {/if}
	{/if}
    </form>
{/block}
