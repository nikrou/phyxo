{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'History'|translate}</a></li>
    <li class="breadcrumb-item">{'Statistics'|translate}</li>
{/block}

{block name="content"}
    <h3>{$L_STAT_TITLE}</h3>

    <table class="table table-hover table-striped">
	<thead>
	    <tr>
		<th>{$PERIOD_LABEL}</th>
		<th>{'Pages seen'|translate}</th>
		<th></th>
	    </tr>
	</thead>
	<tbody>
	    {if !empty($statrows)}
		{foreach $statrows as $row}
		    <tr>
			<td>{$row.VALUE}</td>
			<td>{$row.PAGES}</td>
			<td><div class="statBar" style="width:{$row.WIDTH}px"></div></td>
		    </tr>
		{/foreach}
	    {/if}
	</tbody>
    </table>
{/block}
