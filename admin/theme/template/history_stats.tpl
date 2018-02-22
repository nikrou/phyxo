{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'History'|translate}</a></li>
    <li class="breadcrumb-item">{'Statistics'|translate}</li>
{/block}

{block name="content"}
    <h3>{$L_STAT_TITLE}</h3>

    <table class="table2" id="dailyStats">
	<tr class="throw">
	    <th>{$PERIOD_LABEL}</th>
	    <th>{'Pages seen'|translate}</th>
	    <th></th>
	</tr>

	{if not empty($statrows)}
	    {foreach from=$statrows item=row}
		<tr>
		    <td style="white-space: nowrap">{$row.VALUE}</td>
		    <td class="number">{$row.PAGES}</td>
		    <td><div class="statBar" style="width:{$row.WIDTH}px"></div></td>
		</tr>
	    {/foreach}
	{/if}

    </table>
{/block}
