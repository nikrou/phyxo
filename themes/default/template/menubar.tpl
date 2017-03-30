{if !empty($blocks) }
    {foreach $blocks as $id => $block}
	<div id="{$id}">
	    {if not empty($block->template)}
		{include file=$block->template|@get_extent:$id }
	    {else}
		{$block->raw_content}
	    {/if}
	</div>
    {/foreach}
{/if}
