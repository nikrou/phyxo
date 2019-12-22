{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'History'|translate}</a></li>
    <li class="breadcrumb-item">{'Search'|translate}</li>
{/block}

{block name="content"}
    <form method="post" name="filter" action="{$F_ACTION}">
	<p><button type="button" class="btn btn-submit" data-toggle="collapse" data-target="#filter">{'Search filter'|translate}</button></p>

	<div class="fieldset collapse" id="filter">
	    <h3>{'Filter'|translate}</h3>
	    <div class="row">
		<div class="col-auto">
		    <div>
			<label>
			    {'Date'|translate}
			    <i class="fa fa-calendar"></i>
			    <input type="date" class="form-control" name="start"/>
			</label>
		    </div>
		    <div>
			<label>
			    {'End-Date'|translate}
			    <i class="fa fa-calendar"></i>
			    <input type="date" class="form-control" name="end"/>
			</label>
		    </div>
		</div>
		<div class="col-auto">
		    <div>
			<label>
			    {'Element type'|translate}
			    <select class="custom-select" name="types[]" multiple="multiple" size="4">
				{html_options options=$type_option_values selected=$type_option_selected}
			    </select>
			</label>
		    </div>
		</div>
		<div class="col-auto">
		    <div>
			<label>
			    {'Image id'|translate}
			    <input class="form-control" name="image_id" value="{$IMAGE_ID}" type="text" size="5">
			</label>
		    </div>
		    <div>
			<label>
			    {'Filename'|translate}
			    <input class="form-control" name="filename" value="{$FILENAME}" type="text" size="12">
			</label>
		    </div>
		    <div>
			<label>
			    {'Thumbnails'|translate}
			    <select class="custom-select" name="display_thumbnail">
				{html_options options=$display_thumbnails selected=$display_thumbnail_selected}
			    </select>
			</label>
		    </div>
		</div>
		<div class="col-auto">
		    <div>
			<label>
			    {'User'|translate}
			    <select class="custom-select" name="user">
				<option value="-1">------------</option>
				{html_options options=$user_options selected=$user_options_selected}
			    </select>
			</label>
		    </div>
		    <div>
			<label>
			    {'IP'|translate}
			    <input class="form-control" name="ip" value="{$IP}" type="text" size="12">
			</label>
		    </div>
		</div>
	    </div>
	    <input class="btn btn-submit" type="submit" name="submit" value="{'Submit'|translate}">
	</div>
    </form>

    {if isset($search_summary)}
	<div class="fieldset">
	    <h3>{'Summary'|translate}</h3>

	    <ul>
		<li>{$search_summary.NB_LINES}, {$search_summary.FILESIZE}</li>
		<li>
		    {$search_summary.USERS}
		    <ul>
			<li>{$search_summary.MEMBERS}</li>
			<li>{$search_summary.GUESTS}</li>
		    </ul>
		</li>
	    </ul>
	</div>
    {/if}

    {if !empty($navbar)}{include file="navigation_bar.tpl"}{/if}

    <table class="table table-hover table-striped">
	<thead>
	    <tr>
		<th>{'Date'|translate}</th>
		<th>{'Time'|translate}</th>
		<th>{'User'|translate}</th>
		<th>{'IP'|translate}</th>
		<th>{'Element'|translate}</th>
		<th>{'Element type'|translate}</th>
		<th>{'Section'|translate}</th>
		<th>{'Album'|translate} / {'Tags'|translate}</th>
	    </tr>
	</thead>
	{if !empty($search_results)}
	    {foreach $search_results as $detail}
		<tr>
		    <td class="hour">{$detail.DATE}</td>
		    <td class="hour">{$detail.TIME}</td>
		    <td>{$detail.USER}</td>
		    <td class="IP">{$detail.IP}</td>
		    <td>{$detail.IMAGE}</td>
		    <td>{$detail.TYPE}</td>
		    <td>{$detail.SECTION}</td>
		    <td>{$detail.CATEGORY}{if $detail.TAGS}&nbsp;/&nbsp;{/if}{$detail.TAGS}</td>
		</tr>
	    {/foreach}
	{/if}
    </table>

    {if !empty($navbar) }{include file="navigation_bar.tpl"}{/if}
{/block}
