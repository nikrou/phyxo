{if !empty($blocks) }
    {foreach $blocks as $block}
	{if !empty($block->template)}
	    {include file=$block->template}
	{else}
	    {$block->raw_content}
	{/if}
    {/foreach}
{/if}
