{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Themes'|translate}</a></li>
    <li class="breadcrumb-item">{'Installed Themes'|translate}</li>
{/block}

{block name="content"}
    {include file='include/colorbox.inc.tpl'}
    <div id="themesContent">
		{assign var='field_name' value='null'} {* <!-- 'counter' for fieldset management --> *}
		{foreach from=$tpl_themes item=theme}
		    {if $field_name != $theme.STATE}
			{if $field_name != 'null'}
    			</div>
  				</div>
			{/if}

		<div class="fieldset">
		    <h3>
			{if $theme.STATE == 'active'}
			    {'Active Themes'|translate}
			{else}
			    {'Inactive Themes'|translate}
			{/if}
		    </h3>
		    <div class="themeBoxes">
			{assign var='field_name' value=$theme.STATE}
	    {/if}

	    {if not empty($theme.AUTHOR)}
		{if not empty($theme.AUTHOR_URL)}
		    {assign var='author' value="<a href='%s'>%s</a>"|@sprintf:$theme.AUTHOR_URL:$theme.AUTHOR}
		{else}
		    {assign var='author' value='<u>'|cat:$theme.AUTHOR|cat:'</u>'}
		{/if}
	    {/if}
	    {if not empty($theme.VISIT_URL)}
		{assign var='version' value="<a class='externalLink' href='"|cat:$theme.VISIT_URL|cat:"'>"|cat:$theme.VERSION|cat:"</a>"}
	    {else}
		{assign var='version' value=$theme.VERSION}
	    {/if}

	    <div class="themeBox{if $theme.IS_DEFAULT} themeDefault{/if}">
		<div class="themeName">
		    {$theme.NAME} {if $theme.IS_DEFAULT}<em>({'default'|translate})</em>{/if} {if $theme.IS_MOBILE}<em>({'Mobile'|translate})</em>{/if}
		    <a class="showInfo" title="{if !empty($author)}{'By %s'|translate:$author} | {/if}{'Version'|translate} {$version}<br/>{$theme.DESC|@escape:'html'}"><i class="fa fa-info-circle"></i></a>
		</div>
		<div class="themeShot"><a href="{$theme.SCREENSHOT}" class="preview-box icon-zoom-in" title="{$theme.NAME}"><img src="{$ROOT_URL}{$theme.SCREENSHOT}" alt=""></a></div>
		<div class="themeActions">
		    <div>
			{if $theme.STATE == 'active'}
			    {if $theme.DEACTIVABLE}
				<a href="{$deactivate_baseurl}{$theme.ID}" title="{'Forbid this theme to users'|translate}">{'Deactivate'|translate}</a>
			    {else}
				<span title="{$theme.DEACTIVATE_TOOLTIP}">{'Deactivate'|translate}</span>
			    {/if}

			    {if not $theme.IS_DEFAULT}
				| <a href="{$set_default_baseurl}{$theme.ID}" title="{'Set as default theme for unregistered and new users'|translate}">{'Default'|translate}</a>
			    {/if}
			    {if $theme.ADMIN_URI}
				<br><a href="{$theme.ADMIN_URI}" title="{'Configuration'|translate}">{'Configuration'|translate}</a>
			    {/if}
			{else}
			    {if $theme.ACTIVABLE}
				<a href="{$activate_baseurl}{$theme.ID}" title="{'Make this theme available to users'|translate}">{'Activate'|translate}</a>
			    {else}
				<span title="{$theme.ACTIVATE_TOOLTIP}">{'Activate'|translate}</span>
			    {/if}
			    |
			    {if $theme.DELETABLE}
				<a href="{$delete_baseurl}{$theme.ID}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');" title="{'Delete this theme'|translate}">{'Delete'|translate}</a>
			    {else}
				<span title="{$theme.DELETE_TOOLTIP}">{'Delete'|translate}</span>
			    {/if}
			{/if}
		    </div>
		</div> <!-- themeActions -->
	    </div>

	{/foreach}
		    </div> <!-- themeBoxes -->
		</div>

    </div> <!-- themesContent -->
{/block}
