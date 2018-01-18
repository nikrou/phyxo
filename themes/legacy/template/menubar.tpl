{if !empty($blocks) }
    <div id="menubar">
	{foreach $blocks as $block}
	    <dl id="{$id}">
		{if not empty($block->template)}
		    {include file=$block->template}
		{else}
		    {$block->raw_content}
		{/if}
	    </dl>
	{/foreach}
    </div><div id="menuSwitcher"></div>
{/if}
