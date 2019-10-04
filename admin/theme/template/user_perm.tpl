{extends file="__layout.tpl"}

{block name="content"}
    <h2>{$TITLE}</h2>

    {if isset($categories_because_of_groups) }
	<div class="fieldset">
	    <h3>{'Albums authorized thanks to group associations'|translate}</h3>

	    <ul>
		{foreach $categories_because_of_groups as $cat }
		    <li>{$cat}</li>
		{/foreach}
	    </ul>
	</div>
    {/if}

    <div class="fieldset">
	<h3>{'Other private albums'|translate}</h3>

	<form method="post" action="{$F_ACTION}">
	    {include file="double_select.tpl"}
	</form>
    </div>
{/block}
