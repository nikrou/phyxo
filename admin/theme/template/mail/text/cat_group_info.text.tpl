{extends file="mail/text/__layout.text.tpl"}

{block name="content"}
    {'Hello,'|translate}

    {'Discover album:'|translate} {$CAT_NAME}
    {$LINK}

    {$CPL_CONTENT}

    {'See you soon.'|translate}
{/block}
