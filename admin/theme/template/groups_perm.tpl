{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Groups'|translate}</a></li>
    <li class="breadcrumb-item">{'Permissions'|translate}</li>
{/block}

{block name="content"}
    {if not empty($groups)}
	<ul class="inline-groups">
	    {foreach $groups as $group}
		<li>
		    {$group.NAME}
		    <a href="{$group.U_PERM}" title="{'Permissions'|translate}">{'Permissions'|translate}</a>
		</li>
	    {/foreach}
	</ul>
    {else}
	<h3>{$TITLE}</h3>
	<form method="post" action="{$F_ACTION}">
	    {$DOUBLE_SELECT}
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	</form>
	<p>{'Only private albums are listed'|translate}</p>
    {/if}
{/block}
