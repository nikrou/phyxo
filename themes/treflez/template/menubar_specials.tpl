{foreach $blocks.mbSpecials->data as $link}
    <a class="dropdown-item" href="{$link.URL}" title="{$link.TITLE}"{if isset($link.REL)} {$link.REL}{/if}>{$link.NAME}</a>
{/foreach}
