{extends file="index.tpl"}

{block name="outer-context"}{/block}

{block name="content"}
    <div class="titrePage">
	<ul class="categoryActions"></ul>
	<h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'User comments'|translate}</h2>
    </div>

    <form class="filter" action="{$F_ACTION}" method="get">
	<fieldset>
	    <legend>{'Filter'|translate}</legend>
	    <ul>
		<li>
		    <label for="filter_keyword">{'Keyword'|translate}</label>
		</li>
		<li>
		    <input type="text" id="filter_keyword" name="keyword" value="{$F_KEYWORD}">
		</li>
	    </ul>

	    <ul>
		<li>
		    <label for="filter_author">{'Author'|translate}</label>
		</li>
		<li>
		    <input type="text" id="filter_author" name="author" value="{$F_AUTHOR}">
		</li>
	    </ul>

	    <ul>
		<li>
		    <label for="filter_album">{'Album'|translate}</label>
		</li>
		<li>
		    <select id="filter_album" name="cat">
			<option value="0">------------</option>
			{html_options options=$categories selected=$categories_selected}
		    </select>
		</li>
	    </ul>

	    <ul>
		<li>
		    <label for="filter_since">{'Since'|translate}</label>
		</li>
		<li>
		    <select id="filter_since" name="since">
			{html_options options=$since_options selected=$since_options_selected}
		    </select>
		</li>
	    </ul>
	</fieldset>

	<fieldset>
	    <legend>{'Display'|translate}</legend>
	    <ul>
		<li>
		    <label>{'Sort by'|translate}</label>
		</li>
		<li>
		    <select name="sort_by">
			{html_options options=$sort_by_options selected=$sort_by_options_selected}
		    </select>
		</li>
	    </ul>

	    <ul>
		<li>
		    <label>{'Sort order'|translate}</label>
		</li>
		<li>
		    <select name="sort_order">
			{html_options options=$sort_order_options selected=$sort_order_options_selected}
		    </select>
		</li>
	    </ul>

	    <ul>
		<li>
		    <label>{'Number of items'|translate}</label>
		</li>
		<li>
		    <select name="items_number">
			{html_options options=$item_number_options selected=$item_number_options_selected}
		    </select>
		</li>
	    </ul>
	</fieldset>

	<p><input type="submit" value="{'Filter and display'|translate}"></p>
    </form>

    {if !empty($navbar) }{include file="_navigation_bar.tpl"}{/if}
    {if isset($comments)}
	<div id="comments">
	    {include file="_comment_list.tpl" comment_derivative_params=$derivative_params}
	</div>
    {/if}
{/block}
