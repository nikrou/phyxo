{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Themes'|translate}</a></li>
    <li class="breadcrumb-item">{'Installed Themes'|translate}</li>
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
    {foreach $theme_states as $theme_state}
	<div class="extensions themes state state-{$theme_state}">
	    {if $theme_state === 'active'}
		<h3>{'Active Themes'|translate}</h3>
	    {else}
		<h3>{'Inactive Themes'|translate}</h3>
	    {/if}

	    <div class="card-deck">
		{foreach $themes as $theme}
		    {if $theme.state === $theme_state}
			{if !empty($theme.VISIT_URL)}
			    {assign var="version" value="<a class='externalLink' href='"|cat:$theme.VISIT_URL|cat:"'>"|cat:$theme.VERSION|cat:"</a>"}
			{else}
			    {assign var="version" value=$theme.VERSION}
			{/if}

			<div class="extension theme card">
			    <div class="card-header">
				{$theme.NAME} {if $theme.IS_DEFAULT}<em>({'default'|translate})</em>{/if} {if $theme.IS_MOBILE}<em>({'Mobile'|translate})</em>{/if}
				{'Version'|translate} {$version}
			    </div>
			    <div class="card-body">
				<a href="{$ROOT_URL}{$theme.SCREENSHOT}" class="preview-box icon-zoom-in" title="{$theme.NAME}">
				    <img class="card-img-top" src="{$ROOT_URL}{$theme.SCREENSHOT}" alt="">
				</a>
			    </div>
			    <div class="actions btn-group btn-group-sm">
				{if $theme.state === 'active'}
				    {if $theme.DEACTIVABLE}
					<a href="{$theme.deactivate}" class="btn btn-sm btn-info" title="{'Forbid this theme to users'|translate}"
						data-type="{$EXT_TYPE}" data-ext-id="{$theme.ID}">
					    {'Deactivate'|translate}
					</a>
				    {else}
					<button type="button" class="btn btn-sm btn-info disabled" title="{$theme.DEACTIVATE_TOOLTIP}">{'Deactivate'|translate}</button>
				    {/if}

				    {if !$theme.IS_DEFAULT}
					<a href="{$theme.set_default}" class="btn btn-sm btn-success" title="{'Set as default theme for unregistered and new users'|translate}">
					    {'Default'|translate}
					</a>
				    {/if}

				    {if $theme.ADMIN_URI}
					<a class="btn btn-sm btn-warning" href="{$theme.ADMIN_URI}" title="{'Configuration'|translate}">{'Configuration'|translate}</a>
				    {/if}
				{else}
				    {if $theme.ACTIVABLE}
					<a href="{$theme.activate}" class="btn btn-sm btn-submit" title="{'Make this theme available to users'|translate}">
					    {'Activate'|translate}
					</a>
				    {else}
					<button class="btn btn-sm btn-submit disabled" title="{$theme.ACTIVATE_TOOLTIP}">{'Activate'|translate}</button>
				    {/if}

				    {if $theme.DELETABLE}
					<a href="{$theme.delete}" onClick="return confirm('{'Are you sure?'|translate|escape:javascript}');" class="btn btn-sm btn-danger"
					   title="{'Delete this theme'|translate}">
					    {'Delete'|translate}
					</a>
				    {else}
					<button type="button" class="btn btn-sm btn-danger disabled" title="{$theme.DELETE_TOOLTIP}">{'Delete'|translate}</button>
				    {/if}
				{/if}
			    </div>
			</div>
		    {/if}
		{/foreach}
	    </div>
	</div>
    {/foreach}
{/block}
