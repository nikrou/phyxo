{if !empty($tabsheet)}
    <div id="tabsheet">
	<ul class="tabsheet">
	    {foreach $tabsheet as $sheet}
		<li class="{if ($name == $tabsheet_selected)}selected_tab{else}normal_tab{/if}">
		    <a href="{$sheet.url}"><span>{$sheet.caption}</span></a>
		</li>
	    {/foreach}
	</ul>
    </div>
{/if}
