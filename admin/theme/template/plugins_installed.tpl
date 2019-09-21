{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Plugins'|translate}</a></li>
    <li class="breadcrumb-item">{'Plugin list'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     var ws_url = "{$ws}";
     var pwg_token = "{$csrf_token}";
     var activate_msg = "{'Do you want to activate anyway?'|translate|escape:'javascript'}";
     var confirmMsg  = "{'Are you sure?'|translate|escape:'javascript'}";
     var detailsMsg = {};
     detailsMsg.show = "{'show details'|translate|escape:'javascript'}";
     detailsMsg.hide = "{'hide details'|translate|escape:'javascript'}";
    </script>
{/block}

{block name="content"}
    <div class="showDetails">
	<button type="button" id="deactivateAll" class="btn btn-info{if $plugins_by_state['active'] === 0} collapse{/if}">{'Deactivate all'|translate}</button>
    </div>

    {foreach $plugins_by_state as $plugin_state => $count_plugin_state}
	{if $count_plugin_state === 0}{continue}{/if}

	<div class="extensions state state-{$plugin_state}">
	    {if $plugin_state === 'active'}
		<h3>{'Active Plugins'|translate}</h3>
	    {elseif $plugin_state === 'inactive'}
		<h3>{'Inactive Plugins'|translate}</h3>
	    {elseif $plugin_state === 'missing'}
		<h3>{'Missing Plugins'|translate}</h3>
	    {elseif $plugin_state === 'merged'}
		<h3>{'Obsolete Plugins'|translate}</h3>
	    {/if}
	    <div>
		{foreach $plugins as $plugin}
		    {if $plugin.state === $plugin_state}
			<div class="row extension plugin" id="plugin-{$plugin.ID}">
			    <div class="col-2">
				<div>{$plugin.NAME}</div>
				<div class="version">{'Version'|translate} {$plugin.VERSION}</div>
			    </div>
			    <div class="col-10">
				<div>
				    {if $plugin.state === 'active'}
					<button class="btn btn-sm btn-info deactivate" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.ID}">
					    {'Deactivate'|translate}
					</button>
					<button class="btn btn-sm btn-success restore" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.ID}">
					    {'Restore'|translate}
					</button>
				    {elseif $plugin.state === 'inactive'}
					<button class="btn btn-sm btn-submit activate" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.ID}">
					    {'Activate'|translate}
					</button>
					<button class="btn btn-sm btn-danger delete" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.ID}">
					    {'Delete'|translate}
					</button>
				    {elseif $plugin.state === 'missing'}
					<button class="btn btn-sm btn-warning uninstall" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.ID}">
					    {'Uninstall'|translate}
					</button>
				    {elseif $plugin.state === 'merged'}
					<button class="btn btn-sm btn-danger delete" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.ID}">
					    {'Delete'|translate}
					</button>
				    {/if}
				</div>
				{if not empty($plugin.AUTHOR)}
				    <div class="author">
					{if not empty($plugin.AUTHOR_URL)}
					    {assign var='author' value="<a href='%s'>%s</a>"|@sprintf:$plugin.AUTHOR_URL:$plugin.AUTHOR}
					{else}
					    {assign var='author' value='<u>'|cat:$plugin.AUTHOR|cat:'</u>'}
					{/if}
					{'By %s'|translate:$author}

					{if !empty($plugin.VISIT_URL)}
					    &nbsp;|&nbsp;<a class="externalLink" href="{$plugin.VISIT_URL}">{'Visit plugin site'|translate}</a>
					{/if}
				    </div>
				{/if}
				{if $plugin_state !== 'missing' && !isset($incompatible_plugins[$plugin.ID])}
				    <p>
					<button type="button" class="btn btn-link" data-target="#description-{$plugin.ID}" data-toggle="collapse">
					    <i class="fa fa-plus-square-o"></i>
					    {'show details'|translate}
					</button>
				    </p>
				{/if}

				<div id="description-{$plugin.ID}" class="description{if $plugin_state !== 'missing' && !isset($incompatible_plugins[$plugin.ID])}} collapse{/if}">
				    <p{if $plugin_state === 'missing'} class="text-danger"{/if}>{$plugin.DESC}</p>
				    {if isset($incompatible_plugins[$plugin.ID])}
					<p class="text-warning">{'Warning! This plugin does not seem to be compatible with this version of Phyxo.'|translate}</p>
				    {/if}
				</div>
			    </div>
			</div>
		    {/if}
		{/foreach}
	    </div>
	</div>
    {/foreach}
{/block}
