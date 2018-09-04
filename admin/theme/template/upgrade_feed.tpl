{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item">{'Database upgrade'|translate}</li>
{/block}

{block name="content"}
    {$upgrade_content}

    <p>All database upgrades have been made. Go to <a href="./">dashboard</a></p>
{/block}
