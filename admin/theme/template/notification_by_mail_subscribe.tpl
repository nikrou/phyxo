{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Notification'|translate}</a></li>
    <li class="breadcrumb-item">{'Subscribe'|translate}</li>
{/block}

{block name="content"}
    {include file='include/autosize.inc.tpl'}

    <form method="post" name="notification_by_mail" id="notification_by_mail" action="{$F_ACTION}">
		{if isset($REPOST_SUBMIT_NAME)}
			<div class="fieldset>
				<input class="btn btn-submit" type="submit" value="{'Continue processing treatment'|translate}" name="{$REPOST_SUBMIT_NAME}">
			</div>
		{/if}

		<div class="fieldset">
			<h3>{'Subscribe/unsubscribe users'|translate}</h3>
			<p><i>{'Warning: subscribing or unsubscribing will send mails to users'|translate}</i></p>
			{$DOUBLE_SELECT}
		</div>
    </form>
{/block}
