{combine_script id='lightaccordion' load='footer' path='admin/theme/js/light.accordion.js'}
{footer_script}
$('#menubar').lightAccordion({
active: {$ACTIVE_MENU}
});
{/footer_script}

<div id="menubar">
    <dl>
	<dt><i class="icon-picture"> </i><span>{'Photos'|translate}&nbsp;</span></dt>
	<dd>
	    <ul>
		<li><a href="{$U_ADD_PHOTOS}"><i class="icon-plus-circled"></i>{'Add'|translate}</a></li>
		<li><a href="{$U_RATING}"><i class="icon-star"></i>{'Rating'|translate}</a></li>
		<li><a href="{$U_TAGS}"><i class="icon-tags"></i>{'Tags'|translate}</a></li>
		<li><a href="{$U_RECENT_SET}"><i class="icon-clock"></i>{'Recent photos'|translate}</a></li>
		<li><a href="{$U_BATCH}"><i class="icon-pencil"></i>{'Batch Manager'|translate}</a></li>
		{if !empty($NB_PHOTOS_IN_CADDIE)}
		    <li><a href="{$U_CADDIE}"><i class="icon-flag"></i>{'Caddie'|translate}<span class="adminMenubarCounter">{$NB_PHOTOS_IN_CADDIE}</span></a></li>
		{/if}
	    </ul>
	</dd>
    </dl>
    <dl>
	<dt><i class="icon-sitemap"> </i><span>{'Albums'|translate}&nbsp;</span></dt>
	<dd>
	    <ul>
		<li><a href="{$U_ALBUMS}"><i class="icon-folder-open"></i>{'Manage'|translate}</a></li>
		<li><a href="{$U_ALBUMS_OPTIONS}"><i class="icon-pencil"></i>{'Properties'|translate}</a></li>
	    </ul>
	</dd>
    </dl>
    <dl>
	<dt><i class="icon-users"> </i><span>{'Users'|translate}&nbsp;</span></dt>
	<dd>
	    <ul>
		<li><a href="{$U_USERS}"><i class="icon-user-add"></i>{'Manage'|translate}</a></li>
		<li><a href="{$U_GROUPS}"><i class="icon-group"></i>{'Groups'|translate}</a></li>
		<li><a href="{$U_NOTIFICATION_BY_MAIL}"><i class="icon-mail-1"></i>{'Notification'|translate}</a></li>
	    </ul>
	</dd>
    </dl>
    <dl>
	<dt><i class="icon-puzzle"> </i><span>{'Plugins'|translate}&nbsp;</span></dt>
	<dd>
	    <ul>
		<li><a href="{$U_PLUGINS}"><i class="icon-equalizer"></i>{'Manage'|translate}</a></li>
	    </ul>
	    <div id="pluginsMenuSeparator"></div>
	    {if !empty($plugin_menu_items)}
		<ul class="scroll">
		    {foreach from=$plugin_menu_items item=menu_item}
			<li><a href="{$menu_item.URL}">{$menu_item.NAME}</a></li>
		    {/foreach}
		</ul>
	    {/if}
	</dd>
    </dl>
    <dl>
	<dt><i class="icon-wrench"> </i><span>{'Tools'|translate}&nbsp;</span></dt>
	<dd>
	    <ul>
		{if $ENABLE_SYNCHRONIZATION}
		    <li><a href="{$U_CAT_UPDATE}"><i class="icon-exchange"></i>{'Synchronize'|translate}</a></li>
		    <li><a href="{$U_SITE_MANAGER}"><i class="icon-flow-branch"></i>{'Site manager'|translate}</a></li>
		{/if}
		<li><a href="{$U_HISTORY_STAT}"><i class="icon-signal"></i>{'History'|translate}</a></li>
		<li><a href="{$U_MAINTENANCE}"><i class="icon-tools"></i>{'Maintenance'|translate}</a></li>
		{if isset($U_COMMENTS)}
		    <li>
			<a href="{$U_COMMENTS}"><i class="icon-chat"></i>{'Comments'|translate}
			    {if !empty($NB_PENDING_COMMENTS)}
				<span class="adminMenubarCounter" title="{'%d waiting for validation'|translate:$NB_PENDING_COMMENTS}">{$NB_PENDING_COMMENTS}</span>
			    {/if}
			</a>
		    </li>
		{/if}
		<li><a href="{$U_UPDATES}"><i class="icon-arrows-cw"></i>{'Updates'|translate}</a></li>
	    </ul>
	</dd>
    </dl>
    <dl>
	<dt><i class="icon-cog"> </i><span>{'Configuration'|translate}&nbsp;</span></dt>
	<dd>
	    <ul>
		<li><a href="{$U_CONFIG_GENERAL}"><i class="icon-cog-alt"></i>{'Options'|translate}</a></li>
		<li><a href="{$U_CONFIG_MENUBAR}"><i class="icon-menu"></i>{'Menu Management'|translate}</a></li>
		<li><a href="{$U_CONFIG_LANGUAGES}"><i class="icon-language"></i>{'Languages'|translate}</a></li>
		<li><a href="{$U_CONFIG_THEMES}"><i class="icon-brush"></i>{'Themes'|translate}</a></li>
	    </ul>
	</dd>
    </dl>
    {if $U_DEV_VERSION}
	<dl>
	    <dt><i class="icon-cw">&nbsp;</i><span>{'Development'|translate}</span></dt>
	    <dd>
		<ul>
		    {if $U_DEV_API}
			<li><a href="{$U_DEV_API}">API</a></li>
		    {/if}
		    <li><a href="{$U_DEV_JS_TESTS}">{'Javascript tests'|translate}</a></li>
		</ul>
	    </dd>
	</dl>
    {/if}
</div> <!-- menubar -->
