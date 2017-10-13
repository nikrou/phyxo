{extends file="__layout.tpl"}

{block name="main-content"}
    {if !empty($chronology_navigation_bars)}
	{foreach $chronology_navigation_bars as $bar}
	    <div class="calendarBar">
		{if isset($bar.previous)}
		    <div class="fleft"><a href="{$bar.previous.URL}">&laquo;&nbsp;{$bar.previous.LABEL}</a></div>
		{/if}
		{if isset($bar.next)}
		    <div class="fright"><a href="{$bar.next.URL}">{$bar.next.LABEL}&nbsp;&raquo;</a></div>
		{/if}
		{if empty($bar.items)}
		    &nbsp;
		{else}
		    {foreach $bar.items as $item}
			{if !isset($item.URL)}
			    <span class="calItem">{$item.LABEL}</span>
			{else}
			    <a class="calItem"{if isset($item.NB_IMAGES)} title="{$item.NB_IMAGES|translate_dec:'%d photo':'%d photos'}"{/if} href="{$item.URL}">{$item.LABEL}</a>
			{/if}
		    {/foreach}
		{/if}
	    </div>
	{/foreach}
    {/if}

    {if !empty($chronology_calendar.calendar_bars)}
	{foreach $chronology_calendar.calendar_bars as $bar}
	    <div class="calendarCalBar">
		<span class="calCalHead"><a href="{$bar.U_HEAD}">{$bar.HEAD_LABEL}</a>  ({$bar.NB_IMAGES})</span><br>
		{foreach $bar.items as $item}
		    <span class="calCal{if !isset($item.URL)}Empty{/if}">
			{if isset($item.URL)}
			    <a href="{$item.URL}">{$item.LABEL}</a>
			{else}
			    {$item.LABEL}
			{/if}
			{if isset($item.NB_IMAGES)}({$item.NB_IMAGES}){/if}
		    </span>
		{/foreach}
	    </div>
	{/foreach}
    {/if}

    {if isset($chronology_calendar.month_view)}
	<table class="calMonth">
	    <thead>
		<tr>
		    {foreach $chronology_calendar.month_view.wday_labels as $wday}
			<th>{$wday}</th>
		    {/foreach}
		</tr>
	    </thead>
	    {foreach $chronology_calendar.month_view.weeks as $week}
		<tr>
 		    {foreach $week as $day}
 			{if !empty($day)}
 			    {if isset($day.IMAGE)}
 				<td class="calDayCellFull">
	 			    <div class="calBackDate">{$day.DAY}</div>
				    <div class="calForeDate">{$day.DAY}</div>
	 			    <div class="calImg">
					<a href="{$day.U_IMG_LINK}">
 					    <img src="{$day.IMAGE}" alt="{$day.IMAGE_ALT}" title="{$day.NB_ELEMENTS|translate_dec:'%d photo':'%d photos'}">
					</a>
				    </div>
 			    {else}
 				    <td class="calDayCellEmpty">{$day.DAY}
 			    {/if}
 			{else}{*blank cell first or last row only*}
 			    <td>
 			{/if}
 			    </td>
 		    {/foreach}{*day in week*}
		</tr>
	    {/foreach}{*week in month*}
	</table>
    {/if}
    {if $thumbnails}
	{include file="_thumbnails.tpl"}
	{if !empty($thumb_navbar)}
            {include file="_navigation_bar.tpl" navbar=$thumb_navbar}
	{/if}
    {/if}
{/block}
