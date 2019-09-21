{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Plugins'|translate}</a></li>
    <li class="breadcrumb-item">{'Updates'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     var ws_url = '{$ws}';
     var pwg_token = '{$csrf_token}';
     var extType = '{$EXT_TYPE}';
     var confirmMsg  = '{'Are you sure?'|translate|escape:'javascript'}';
     var errorHead   = '{'ERROR'|translate|escape:'javascript'}';
     var successHead = '{'Update Complete'|translate|escape:'javascript'}';
     var errorMsg    = '{'an error happened'|translate|escape:'javascript'}';
     var restoreMsg  = '{'Reset ignored updates'|translate|escape:'javascript'}';
    </script>
{/block}

{block name="content"}
    <div class="actions">
	{if (count($update_plugins) - $SHOW_RESET)>0}
	    <button type="button" class="btn btn-submit" id="updateAll">{'Update All'|translate}</button>
	    <button type="button" class="btn btn-warning" id="ignoreAll">{'Ignore All'|translate}</button>
	{/if}
	<button type="button" class="btn btn-warning{if $SHOW_RESET===0} collapse{/if}" id="resetIgnored">
	    {'Reset ignored updates'|translate}
	    &nbsp;<small>(<span class="count">{$SHOW_RESET}</span>)</small>
	</button>
    </div>

    <div class="please-wait collapse">
	{'Please wait...'|translate}
    </div>

    <p id="up-to-date"{if (count($update_plugins) - $SHOW_RESET)>0} class="collapse"{/if}>{'All %s are up to date.'|sprintf:$EXT_TYPE|translate}</p>

    {if (count($update_plugins) - $SHOW_RESET)>0}
	<div class="extensions">
	    <h3>{'Plugins'|translate}</h3>
	    {foreach $update_plugins as $plugin}
		<div class="extension row{if $plugin.IGNORED} ignore{/if}" id="plugin_{$plugin.EXT_ID}">
		    <div class="col-2">
			<div>{$plugin.EXT_NAME}</div>
			<div>{'Version'|translate} {$plugin.CURRENT_VERSION}</div>
		    </div>
		    <div class="col-10">
			<button type="button" class="btn btn-sm btn-submit install" data-redirect="{$INSTALL_URL}"
				data-type="{$EXT_TYPE}" data-ext-id="{$plugin.EXT_ID}" data-revision-id="{$plugin.REVISION_ID}">
			    {'Install'|translate}
			</button>
			<a class="btn btn-sm btn-success" href="{$plugin.URL_DOWNLOAD}">{'Download'|translate}</a>
			<button type="button" class="btn btn-sm btn-warning ignore" data-type="{$EXT_TYPE}" data-ext-id="{$plugin.EXT_ID}">
			    {'Ignore this update'|translate}
			</button>

			<div class="extension description" id="desc_{$plugin.EXT_ID}">
			    <em>{'Downloads'|translate}: {$plugin.DOWNLOADS}</em>
			    <button type="button" class="btn btn-link show-description" data-target="#description-{$plugin.EXT_ID}" data-toggle="collapse"><i class="fa fa-plus-square-o"></i></button>
			    {'New Version'|translate} : {$plugin.NEW_VERSION} | {'By %s'|translate:$plugin.AUTHOR}
			</div>
			<div class="revision description collapse" id="description-{$plugin.EXT_ID}">
			    <p>{$plugin.REV_DESC|@htmlspecialchars|@nl2br}</p>
			</div>
		    </div>
		</div>
	    {/foreach}
	</div>
    {/if}
{/block}
