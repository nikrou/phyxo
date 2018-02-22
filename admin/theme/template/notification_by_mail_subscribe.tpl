{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Notification'|translate}</a></li>
    <li class="breadcrumb-item">{'Subscribe'|translate}</li>
{/block}

{block name="content"}
    {include file='include/autosize.inc.tpl'}

    <form method="post" name="notification_by_mail" id="notification_by_mail" action="{$F_ACTION}">
	{if isset($REPOST_SUBMIT_NAME)}
	    <fieldset>
		<div class="infos">
		    <input type="submit" value="{'Continue processing treatment'|translate}" name="{$REPOST_SUBMIT_NAME}">
		</div>
	    </fieldset>
	{/if}

	<fieldset>
	    <legend>{'Subscribe/unsubscribe users'|translate}</legend>
	    <p><i>{'Warning: subscribing or unsubscribing will send mails to users'|translate}</i></p>
	    {$DOUBLE_SELECT}
	</fieldset>
    </form>
{/block}
