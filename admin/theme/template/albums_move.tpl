{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Move albums'|translate}</li>
{/block}

{block name="content"}

    <form method="post" action="{$F_ACTION}" class="filter" id="catMove">
	<div class="fieldset">
	    <h3>{'Move albums'|translate}</h3>

	    <label>
			{'Virtual albums to move'|translate}

			<select class="custom-select" name="selection[]" multiple="multiple">
				{html_options options=$category_to_move_options}
			</select>
	    </label>

	    <label>
			{'New parent album'|translate}

			<select class="custom-select" name="parent">
				<option value="0">------------</option>
				{html_options options=$category_parent_options}
			</select>
	    </label>
	</div>

	<p>
	    <input class="btn btn-submit" type="submit" name="submit" value="{'Submit'|translate}">
	</p>

    </form>
{/block}
