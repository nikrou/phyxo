{extends file="__layout.tpl"}

{block name="content"}
    <h2>{$TITLE}</h2>

    {if not empty($groups)}
	<ul class="inline-groups">
	    {foreach from=$groups item=group name=group_loop}
		<li>
		    {$group.NAME}
		    <a href="{$group.U_PERM}" title="{'Permissions'|translate}">{'Permissions'|translate}</a>
		</li>
	    {/foreach}
	</ul>
    {else}
	<form method="post" action="{$F_ACTION}">
	    {$DOUBLE_SELECT}
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	</form>
	<p>{'Only private albums are listed'|translate}</p>
    {/if}
{/block}
