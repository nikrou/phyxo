{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Plugins'|translate}</a></li>
    <li class="breadcrumb-item">{'Other plugins'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     var ws_url = "{$ws}";
     var pwg_token = "{$csrf_token}";
     var phyxo_msg = phyxo_msg || {};
     phyxo_msg.n_plugins_selected = "{'%d plugins selected'|translate|escape:javascript}";
     phyxo_msg.no_plugin_selected = "{'No plugin selected'|translate|escape:javascript}";
     phyxo_msg.one_plugin_selected = "{'One plugin selected'|translate|escape:javascript}";

     phyxo_msg.select_all = "{'All'|translate}";
     phyxo_msg.select_none = "{'None'|translate}";
     phyxo_msg.invert_selection = "{'Invert'|translate}";

     phyxo_msg.processing = "{'Loading...'|translate}";
     phyxo_msg.search = "{'Search'|translate}";
     phyxo_msg.lengthMenu = "{'Display _MENU_ users par page'|translate}";
     phyxo_msg.info = "{'Display from element _START_ to _END_ of _TOTAL_ elements'|translate}";
     phyxo_msg.infoEmpty = "{'Display from element 0 to 0 of 0 elements'|translate}";
     phyxo_msg.infoFiltered = "{'(filtered from _MAX_ total records)'|translate}";
     phyxo_msg.loadingRecords = "{'Loading...'|translate}";
     phyxo_msg.zeroRecords = "{'Nothing found'|translate}";
     phyxo_msg.emptyTable = "{'No data available'|translate}";

     phyxo_msg.loading = "{'Loading...'|translate}";
     phyxo_msg.show_plugins = "{'Show %s plugins'|translate}";
     phyxo_msg.no_matching_plugin = "{'No matching plugin found'|translate}";
     phyxo_msg.showing_to_plugins = "{'Showing %s to %s of %s plugins'|translate}";
     phyxo_msg.filtered_from_total_plugins = "{'(filtered from %s total plugins)'|translate}";
     phyxo_msg.search = "{'Search'|translate}";
     phyxo_msg.first = "{'First'|translate}";
     phyxo_msg.previous = "{'Previous'|translate}";
     phyxo_msg.next = "{'Next'|translate}";
     phyxo_msg.last = "{'Last'|translate}";

     var plugins_list_config = {
	 pageLength: 10,
	 language: {
	     processing:     phyxo_msg.loading,
	     search:         phyxo_msg.search,
	     lengthMenu:     phyxo_msg.lengthMenu,
	     info:           phyxo_msg.info,
	     infoEmpty:      phyxo_msg.infoEmpty,
	     infoFiltered:   phyxo_msg.infoFiltered,
	     infoPostFix:    '',
	     loadingRecords: phyxo_msg.loading,
	     zeroRecords:    phyxo_msg.zeroRecords,
	     emptyTable:     phyxo_msg.emptyTable,
	     paginate: {
		 first:      phyxo_msg.first,
		 previous:   phyxo_msg.previous,
		 next:       phyxo_msg.next,
		 last:       phyxo_msg.last,
	     },
	     select: {
		 rows: {
		     _: phyxo_msg.n_plugins_selected,
		     0: phyxo_msg.no_plugin_selected,
		     1: phyxo_msg.one_plugin_selected,
		 },
		 select_all: phyxo_msg.select_all,
		 select_none: phyxo_msg.select_none,
		 invert_selection: phyxo_msg.invert_selection,
	     }
	 }
     };
    </script>
{/block}

{block name="content"}
    {if !empty($plugins)}
	<div class="table-responsive">
	    <table id="plugins-list" class="table table-striped table-hovered" style="width:100%">
		<thead>
		    <tr>
			<th>{'name'|translate}</th>
			<th>{'Author'|translate}</th>
			<th>{'Version'|translate}</th>
			<th>{'Description'|translate}</th>
			<th></th>
		    </tr>
		</thead>
		<tbody>
		    {foreach $plugins as $plugin}
			<tr>
			    <td>{$plugin.EXT_NAME}</td>
			    <td>
				{if not empty($plugin.AUTHOR_URL)}
				    {assign var='author' value="<a href='%s'>%s</a>"|@sprintf:$plugin.AUTHOR_URL:$plugin.AUTHOR}
				{else}
				    {assign var='author' value='<u>'|cat:$plugin.AUTHOR|cat:'</u>'}
				{/if}
				{'By %s'|translate:$author}

				{if !empty($plugin.VISIT_URL)}
				    &nbsp;|&nbsp;<a class="externalLink" href="{$plugin.VISIT_URL}">{'Visit plugin site'|translate}</a>
				{/if}
			    </td>
			    <td>{$plugin.VERSION}</td>
			    <td>
				<div>
				    {$plugin.SMALL_DESC}
				    {if $plugin.BIG_DESC !== $plugin.SMALL_DESC}
					...
					<button type="button" class="btn btn-link" data-target="#description-{$plugin.ID}" data-toggle="collapse">
					    <i class="fa fa-plus-square-o"></i>
					</button>
				    {/if}
				</div>
				{if $plugin.BIG_DESC !== $plugin.SMALL_DESC}
				    <div class="description collapse" id="description-{$plugin.ID}">
					{$plugin.BIG_DESC|nl2br}
				    </div>
				{/if}
			    </td>
			    <td>
				<a class="btn btn-sm btn-submit" href="{$plugin.install}">{'Install'|translate}</a>
				<a class="btn btn-sm btn-success" href="{$plugin.URL_DOWNLOAD}">{'Download'|translate}</a>
			    </td>
			</tr>
		    {/foreach}
		</tbody>
	    </table>
	</div>
    {else}
	<p>{'There is no other plugin available.'|translate}</p>
    {/if}
{/block}
