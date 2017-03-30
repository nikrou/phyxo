<h3>{'Links'|translate}</h3>
<ul>
    {foreach $block->data as $link}
	<li>
	    <a href="{$link.URL}" rel="external"  class="external">{$link.LABEL}</a>
	</li>
    {/foreach}
</ul>
