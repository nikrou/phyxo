{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Rating'|translate}</a></li>
    <li class="breadcrumb-item">{$ratings|count} {'Users'|translate}</li>
{/block}

{block name="content"}

   <form action="{$F_ACTION}" method="GET">
       <p><a class="btn btn-submit" href="#form-filter" data-toggle="collapse">{'Show/Hide form filter'|translate}</a></p>
       <div class="form-filter collapse" id="form-filter">
	   <h3>{'Filter'|translate}</h3>
	   <div class="form-group">
	       <label for="order-by">{'Sort by'|translate}</label>
	       <select class="custom-select" name="order_by" id="order-by">
		   {html_options options=$order_by_options selected=$order_by_options_selected}
	       </select>
	   </div>
	   <div class="form-group">
	       <label for="min-rates">{'Number of rates'|translate}&gt;</label>
	       <input class="form-control" type="text" id="min-rates" size="5" name="f_min_rates" value="{$F_MIN_RATES}">
	   </div>
	   <div class="form-group">
	       <label for="consensus-top-number">{'Consensus deviation'|translate}</label>
	       <input class="form-control" type="text" size="5" id="consensus-top-number" name="consensus_top_number" value="{$CONSENSUS_TOP_NUMBER}">
	       {'Best rated'|translate}
	   </div>
	   <p>
	       <input type="submit" class="btn btn-submit" value="{'Submit'|translate}">
	       <input type="hidden" name="page" value="rating">
	       <input type="hidden" name="section" value="users">
	   </p>
       </div>
   </form>

   {if !empty($navbar) }{include file="navigation_bar.tpl"}{/if}

   <table class="table table-hover table-striped">
       <thead>
	   <tr>
	       <th>{'Username'|translate}</th>
	       <th>{'Last'|translate}</th>
	       <th>{'Number of rates'|translate}</th>
	       <th>{'Average rate'|translate}</th>
	       <th>{'Variation'|translate}</th>
	       <th>{'Consensus deviation'|translate|replace:' ':'<br>'}</th>
	       <th>{'Consensus deviation'|translate|replace:' ':'<br>'} {$CONSENSUS_TOP_NUMBER}</th>
	       {foreach from=$available_rates item=rate}
		   <th class="dtc_rate">{$rate}</th>
	       {/foreach}
	       <th></th>
	   </tr>
       </thead>
       <tbody>
	   {foreach from=$ratings item=rating key=user}
	       <tr id="rate-{$rating.md5sum}">
		   <td>{$user}</td>
		   <td title="First: {$rating.first_date}">{$rating.last_date}</td>
		   <td>{$rating.count}</td>
		   <td>{$rating.avg|number_format:2}</td>
		   <td>{$rating.cv|number_format:3}</td>
		   <td>{$rating.cd|number_format:3}</td>
		   <td>{if !empty($rating.cdtop)}{$rating.cdtop|number_format:3}{/if}</td>
		   {foreach $rating.rates as $rates}
		       <td>{if !empty($rates)}
			   {capture assign=rate_over}{foreach $rates as $rate_arr}{if $rate_arr@index>29}{break}{/if}<img src="{$image_urls[$rate_arr.id].tn}" alt="thumb-{$rate_arr.id}" width="{$TN_WIDTH}" height="{$TN_WIDTH}">{/foreach}{/capture}
			   <a title="{$rate_over|htmlspecialchars}">{$rates|count}</a>
		   {/if}</td>
		   {/foreach}
		   <td>
		       <button
			   data-confirm="{'Are you sure?'|translate}" data-action="{$ROOT_URL}ws.php?format=json&method=pwg.rates.delete"
			   data-data="{ldelim}&quot;user_id&quot;:{$rating.uid}{if !empty({$rating.aid})},&quot;anonymous_id&quot;:&quot;{$rating.aid}&quot;{/if}{rdelim}"
			   data-method="POST" data-delete="#rate-{$rating.md5sum}" data-toggle="modal" data-target="#confirm-delete" class="fa fa-trash"></button>
		   </td>
	       </tr>
	   {/foreach}
       </tbody>
   </table>

   {if !empty($navbar)}{include file="navigation_bar.tpl"}{/if}

   {include file="_modal_delete_confirm.tpl"}
{/block}
