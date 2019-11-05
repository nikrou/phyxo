<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	<meta http-equiv="Content-Type" content="text/html;charset="utf-8"/>
	<title>{$MAIL_TITLE}</title>
    </head>
    <body>
	<style type="text/css">
	 {include file="mail/global-mail-css.tpl"}
	 {include file="mail/mail-css-{$MAIL_THEME}.tpl"}
	</style>

	<table id="bodyTable" cellspacing="0" cellpadding="10" border="0">
	    <tr>
		<td align="center" valign="top">
		    <table id="contentTable" cellspacing="0" cellpadding="0" border="0">
			<tr>
			    <td id="header">
				<div id="title">{$MAIL_TITLE}</div>
				{if !empty($MAIL_SUBTITLE)}<div id="subtitle">{$MAIL_SUBTITLE}</div>{/if}
			    </td>
			</tr>
			<tr>
			    <td id="content">
				<div id="topSpacer"></div>
				{block name="content"}{/block}
			    </td>
			</tr>
		    </table>
		</td>
	    </tr>
            <tr>
		<td id="footer">
		    {'Sent by'|translate} <a href="{$GALLERY_URL}">{$GALLERY_TITLE}</a>
		    - {'Powered by'|translate} <a href="{$PHYXO_URL}">Phyxo</a>
		    {if !empty($PHYXO_VERSION)}{$PHYXO_VERSION}{/if}

		    - {'Contact'|translate}
		    <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
		</td>
	    </tr>
	</table>
    </body>
</html>
