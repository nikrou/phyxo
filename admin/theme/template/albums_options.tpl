{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Properties'|translate}</a></li>
    <li class="breadcrumb-item">{$TABSHEET_TITLE}</li>
{/block}

{block name="content"}
    <h2>{$TABSHEET_TITLE}</h2>

    <form method="post" action="{$F_ACTION}" id="cat_options">
	<fieldset>
	    <legend>{$L_SECTION}</legend>
	    {$DOUBLE_SELECT}
	</fieldset>
    </form>

{/block}
