{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Rating'|translate}</a></li>
    <li class="breadcrumb-item">{$NB_ELEMENTS} {'Photos'|translate}</li>
{/block}

{block name="content"}
    <form action="{$F_ACTION}" method="GET">
	<p><a class="btn btn-submit" href="#form-filter" data-toggle="collapse">{'Show/Hide form filter'|translate}</a></p>
	<div class="form-filter collapse" id="form-filter">
	    <h3>{'Filter'|translate}</h3>
	    <div class="form-group">
		<label for="order-by">{'Sort by'|translate}</label>
		<select name="order_by" id="order-by" class="form-control">
		    {html_options options=$order_by_options selected=$order_by_options_selected}
		</select>
	    </div>

	    <div class="form-group">
		<label for="users">{'Users'|translate}</label>
		<select name="users" id="users" class="form-control">
		    {html_options options=$user_options selected=$user_options_selected}
		</select>
	    </div>

	    <div class="form-group">
		<label for="display">{'Number of items'|translate}</label>
		<input type="text" class="form-control" name="display" id="display" size="2" value="{$DISPLAY}">
	    </div>
	    <p>
		<input class="btn btn-submit" type="submit" value="{'Submit'|translate}">
		<input type="hidden" name="page" value="rating">
		<input type="hidden" name="section" value="photos">
	    </p>
	</div>
    </form>

    {if !empty($navbar) }{include file="navigation_bar.tpl"}{/if}

    <table class="table table-hover table-striped">
	<thead>
	    <tr>
		<th>{'File'|translate}</th>
		<th>{'Number of rates'|translate}</th>
		<th>{'Rating score'|translate}</th>
		<th>{'Average rate'|translate}</th>
		<th>{'Sum of rates'|translate}</th>
		<th>{'Rate'|translate}/{'Username'|translate}/{'Rate date'|translate}</th>
		<th></th>
	    </tr>
	</thead>
	<tbody>
	    {foreach $images as $image}
		<tr>
		    <td><a href="{$image.U_URL}"><img src="{$image.U_THUMB}" alt="{$image.FILE}" title="{$image.FILE}"></a></td>
		    <td><strong>{$image.NB_RATES}/{$image.NB_RATES_TOTAL}</strong></td>
		    <td><strong>{$image.SCORE_RATE}</strong></td>
		    <td><strong>{$image.AVG_RATE}</strong></td>
		    <td><strong>{$image.SUM_RATE}</strong></td>
		    <td>
			<table class="table">
			    {foreach $image.rates as $rate}
				<tr id="rate-{$rate.md5sum}">
				    <td>{$rate.rate}</td>
				<td><b>{$rate.USER}</b></td>
				<td>{$rate.date}</td>
				<td>
				    <button
					data-confirm="{'Are you sure?'|translate}" data-action="{$ROOT_URL}ws.php?method=pwg.rates.delete"
					data-data="{ldelim}&quot;image_id&quot;:{$image.id},&quot;user_id&quot;:{$rate.user_id}{if !empty({$rate.anonymous_id})},&quot;anonymous_id&quot;:&quot;{$rate.anonymous_id}&quot;{/if}{rdelim}"
					data-method="POST" data-delete="#rate-{$rate.md5sum}" data-toggle="modal" data-target="#confirm-delete" class="btn btn-danger fa fa-trash"></button>
				</td>
			    </tr>
			    {/foreach}{*rates*}
			</table>
		    </td>
		</tr>
	    {/foreach}{*images*}
	</tbody>
    </table>

    {if !empty($navbar)}{include file="navigation_bar.tpl"}{/if}

    {include file="_modal_delete_confirm.tpl"}
{/block}
