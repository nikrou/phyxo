{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Upload Photos'|translate}</a></li>
    <li class="breadcrumb-item">{'FTP + Synchronization'|translate}</li>
{/block}

{block name="content"}
    <div id="ftpPage">
	<p><a href="{$U_CAT_UPDATE}">{'Administration'|translate} &raquo; {'Tools'|translate} &raquo; {'Synchronize'|translate}</a></p>

	{$FTP_HELP_CONTENT}
    </div>
{/block}
