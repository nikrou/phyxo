{extends file="__layout.tpl"}

{block name="content"}
    <h1>{'You are not authorized to access the requested page'|translate}</h1>

    <p><a href="{$ROOT_URL}identification">{'Identification'|translate}</a></p>
    <p><a href="{$ROOT_URL}">{'Home'|translate}</a></p>
{/block}
