{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'Search'|translate}</h2>
{/block}

{block name="main-content"}
    <div class="form-content">
	<form class="filter" method="post" name="search" action="{$F_SEARCH_ACTION}">
	    <div class="fieldset">
		<h3>{'Search for words'|translate}</h3>
		<p>
		    <input type="text" name="search_allwords">
		</p>
		<p>
		    <label><input type="radio" name="mode" value="AND" checked="checked">&nbsp;{'Search for all terms'|translate}</label>
		    <label><input type="radio" name="mode" value="OR">&nbsp;{'Search for any term'|translate}</label>
		</p>

		<h4>{'Apply on properties'|translate}</h4>
		<p>
		    <label><input type="checkbox" name="fields[]" value="name" checked="checked"> {'Photo title'|translate}</label>
		    <label><input type="checkbox" name="fields[]" value="comment" checked="checked"> {'Photo description'|translate}</label>
		    <label><input type="checkbox" name="fields[]" value="file" checked="checked"> {'File name'|translate}</label>
		</p>
	    </div>

	    {if count($AUTHORS)>=1}
		<div class="fieldset">
		    <h3>{'Search for Author'|translate}</h3>
		    <p>
			<select id="authors" placeholder="{'Type in a search term'|translate}" name="authors[]" multiple>
			    {foreach from=$AUTHORS item=author}
				<option value="{$author.author|strip_tags:false|escape:html}">{$author.author|strip_tags:false} ({$author.counter|translate_dec:'%d photo':'%d photos'})</option>
			    {/foreach}
			</select>
		    </p>
		</div>
	    {/if}

	    {if isset($TAGS)}
		<div class="fieldset">
		    <h3>{'Search tags'|translate}</h3>
		    <p>
			<select id="tags" placeholder="{'Type in a search term'|translate}" name="tags[]" multiple>
			    {foreach from=$TAGS item=tag}
				<option value="{$tag.id}">{$tag.name} ({$tag.counter|translate_dec:'%d photo':'%d photos'})</option>
			    {/foreach}
			</select>
			<label><input type="radio" name="tag_mode" value="AND" checked="checked">&nbsp;{'All tags'|translate}</label>
			<label><input type="radio" name="tag_mode" value="OR">&nbsp;{'Any tag'|translate}</label>
		    </p>
		</div>
	    {/if}

	    <div class="fieldset">
		<h3>{'Search by date'|translate}</h3>
		<h4>{'Kind of date'|translate}</h4>
		<p>
		    <label><input type="radio" name="date_type" value="date_creation" checked="checked">&nbsp;{'Creation date'|translate}</label>
		    <label><input type="radio" name="date_type" value="date_available">&nbsp;{'Post date'|translate}</label>
		</p>

		<h4>{'Date'|translate}</h4>
		<p>
		    <select id="start_day" name="start_day">
			<option value="0">--</option>
			{section name=day start=1 loop=32}
			    <option value="{$smarty.section.day.index}" {if $smarty.section.day.index==$START_DAY_SELECTED}selected="selected"{/if}>{$smarty.section.day.index}</option>
			{/section}
		    </select>
		    <select id="start_month" name="start_month">
			{html_options options=$month_list selected=$START_MONTH_SELECTED}
		    </select>
		    <input id="start_year" name="start_year" type="text" size="4" maxlength="4" >
		    <input id="start_linked_date" name="start_linked_date" type="hidden" size="10" disabled="disabled">
		    <a class="date_today" href="#" onClick="document.search.start_day.value={$smarty.now|date_format:"%d"};document.search.start_month.value={$smarty.now|date_format:"%m"};document.search.start_year.value={$smarty.now|date_format:"%Y"};return false;">{'today'|translate}</a>
		</p>

		<h4>{'End-Date'|translate}</h4>
		<p>
		    <select id="end_day" name="end_day">
			<option value="0">--</option>
			{section name=day start=1 loop=32}
			    <option value="{$smarty.section.day.index}" {if $smarty.section.day.index==$END_DAY_SELECTED}selected="selected"{/if}>{$smarty.section.day.index}</option>
			{/section}
		    </select>
		    <select id="end_month" name="end_month">
			{html_options options=$month_list selected=$END_MONTH_SELECTED}
		    </select>
		    <input id="end_year" name="end_year" type="text" size="4" maxlength="4" >
		    <input id="end_linked_date" name="end_linked_date" type="hidden" size="10" disabled="disabled">
		    <a class="date_today" href="#" onClick="document.search.end_day.value={$smarty.now|date_format:"%d"};document.search.end_month.value={$smarty.now|date_format:"%m"};document.search.end_year.value={$smarty.now|date_format:"%Y"};return false;">{'today'|translate}</a>
		</p>
	    </div>

	    <div class="fieldset">
		<h3>{'Search in albums'|translate}</h3>
		<p>
		    <select id="categories" name="cat[]" multiple>
			{html_options options=$category_options selected=$category_options_selected}
		    </select>
		    <label><input type="checkbox" name="subcats-included" value="1" checked="checked"> {'Search in sub-albums'|translate}</label>
		</p>
	    </div>

	    <p>
		<input type="submit" name="submit" value="{'Submit'|translate}">
		<input type="reset" value="{'Reset'|translate}">
	    </p>
	</form>
    </div>
{/block}
