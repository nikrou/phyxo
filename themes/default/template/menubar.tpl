{if !empty($blocks) }
    {foreach $blocks as $id => $block}
	{if not empty($block->template)}
	    {include file=$block->template}
	{else}
	    {$block->raw_content}
	{/if}
    {/foreach}
{/if}
