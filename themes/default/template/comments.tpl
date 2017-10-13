{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'User comments'|translate}</h2>
{/block}

{block name="main-content"}
    <div class="form-content">
	<form action="{$F_ACTION}" method="get">
	    <div class="filters">
		<div class="filter">
		    <h3>{'Filter'|translate}</h3>
		    <p>
			<label for="filter_keyword">{'Keyword'|translate}</label>
			<input type="text" id="filter_keyword" name="keyword" value="{$F_KEYWORD}">
		    </p>

		    <p>
			<label for="filter_author">{'Author'|translate}</label>
			<input type="text" id="filter_author" name="author" value="{$F_AUTHOR}">
		    </p>

		    <p>
			<label for="filter_album">{'Album'|translate}</label>
			<select id="filter_album" name="cat">
			    <option value="0">------------</option>
			    {html_options options=$categories selected=$categories_selected}
			</select>
		    </p>

		    <p>
			<label for="filter_since">{'Since'|translate}</label>
			<select id="filter_since" name="since">
			    {html_options options=$since_options selected=$since_options_selected}
		    </select>
		    </p>
		</div>

		<div class="filter">
		    <h3>{'Display'|translate}</h3>
		    <p>
			<label>{'Sort by'|translate}</label>
		    <select name="sort_by">
			{html_options options=$sort_by_options selected=$sort_by_options_selected}
		    </select>
		    </p>

		    <p>
			<label>{'Sort order'|translate}</label>
			<select name="sort_order">
			    {html_options options=$sort_order_options selected=$sort_order_options_selected}
			</select>
		    </p>

		    <p>
			<label>{'Number of items'|translate}</label>
			<select name="items_number">
			    {html_options options=$item_number_options selected=$item_number_options_selected}
			</select>
		    </p>
		</div>
	    </div>
	    <p><input type="submit" value="{'Filter and display'|translate}"></p>
	</form>

	{if isset($comments)}
	    {include file="_navigation_bar.tpl"}
	    <div class="comments">
		{include file="_comment_list.tpl" comment_derivative_params=$derivative_params}
	    </div>
	{/if}
    </div>
{/block}
