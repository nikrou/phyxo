<ul class="accordion">
    <li>
	<a data-toggle="collapse" href="#child-photos"><i class="fa fa-image"></i> {'Photos'|translate}</a>
	<ul class="collapse" id="child-photos" data-parent=".accordion">
	    <li><a href="{$U_ADD_PHOTOS}"><i class="fa fa-plus-circle"></i> {'Add'|translate}</a></li>
	    <li><a href="{$U_RATING}"><i class="fa fa-star"></i> {'Rating'|translate}</a></li>
	    <li><a href="{$U_TAGS}"><i class="fa fa-tags"></i> {'Tags'|translate}</a></li>
	    <li><a href="{$U_RECENT_SET}"><i class="fa fa-clock-o"></i> {'Recent photos'|translate}</a></li>
	    <li><a href="{$U_BATCH}"><i class="fa fa-tasks"></i> {'Batch Manager'|translate}</a></li>
	    {if !empty($NB_PHOTOS_IN_CADDIE)}
		<li><a href="{$U_CADDIE}"><i class="fa fa-shopping-cart"></i> {'Caddie'|translate}<em class="counter">{$NB_PHOTOS_IN_CADDIE}</em></a></li>
	    {/if}
	</ul>
    </li>
    <li>
	<a data-toggle="collapse" href="#child-album"><i class="fa fa-sitemap"></i> {'Albums'|translate}</a>
	<ul class="collapse" id="child-album" data-parent=".accordion">
	    <li><a href="{$U_ALBUMS}"><i class="fa fa-folder-open"></i>{'Manage'|translate}</a></li>
	    <li><a href="{$U_ALBUMS_OPTIONS}"><i class="fa fa-pencil"></i>{'Properties'|translate}</a></li>
	</ul>
    </li>
    <li>
	<a data-toggle="collapse" href="#child-users"><i class="fa fa-users"></i> {'Users'|translate}</a>
	<ul class="collapse" id="child-users" data-parent=".accordion">
	    <li><a href="{$U_USERS}"><i class="fa fa-user-plus"></i>{'Manage'|translate}</a></li>
	    <li><a href="{$U_GROUPS}"><i class="fa fa-group"></i>{'Groups'|translate}</a></li>
	    <li><a href="{$U_NOTIFICATION_BY_MAIL}"><i class="fa fa-envelope"></i>{'Notification'|translate}</a></li>
	</ul>
    </li>
    <li>
	<a data-toggle="collapse" href="#child-plugins"><i class="fa fa-plug"></i> {'Plugins'|translate}</a>
	<ul class="collapse" id="child-plugins" data-parent=".accordion">
	    <li>
		<a href="{$U_PLUGINS}"><i class="fa fa-sliders"></i>{'Manage'|translate}</a>
		{if !empty($plugin_menu_items)}
		    <ul>
			{foreach $plugin_menu_items as $menu_item}
			    <li><a href="{$menu_item.URL}">{$menu_item.NAME}</a></li>
			{/foreach}
		    </ul>
		{/if}
	    </li>
	</ul>
    </li>
    <li>
	<a data-toggle="collapse" href="#child-tools"><i class="fa fa-wrench"></i> {'Tools'|translate}</a>
	<ul class="collapse" id="child-tools" data-parent=".accordion">
	    {if $ENABLE_SYNCHRONIZATION}
		<li><a href="{$U_CAT_UPDATE}"><i class="fa fa-exchange"></i>{'Synchronize'|translate}</a></li>
		<li><a href="{$U_SITE_MANAGER}"><i class="fa fa-code-fork"></i>{'Site manager'|translate}</a></li>
	    {/if}
	    <li><a href="{$U_HISTORY_STAT}"><i class="fa fa-bar-chart"></i>{'History'|translate}</a></li>
	    <li><a href="{$U_MAINTENANCE}"><i class="fa fa-cogs"></i>{'Maintenance'|translate}</a></li>
	    {if isset($U_COMMENTS)}
		<li>
		    <a href="{$U_COMMENTS}"><i class="fa fa-comments"></i>{'Comments'|translate}
			{if !empty($NB_PENDING_COMMENTS)}
			    <span class="counter" title="{'waiting_for_validation'|translate:['count' => $NB_PENDING_COMMENTS]}">{$NB_PENDING_COMMENTS}</span>
			{/if}
		    </a>
		</li>
	    {/if}
	    <li><a href="{$U_UPDATE}"><i class="fa fa-refresh"></i>{'Updates'|translate}</a></li>
	</ul>
    </li>
    <li>
	<a data-toggle="collapse" href="#child-configuration"><i class="fa fa-cog"></i> {'Configuration'|translate}</a>
	<ul class="collapse" id="child-configuration" data-parent=".accordion">
	    <li><a href="{$U_CONFIG_GENERAL}"><i class="fa fa-cogs"></i>{'Options'|translate}</a></li>
	    <li><a href="{$U_CONFIG_MENUBAR}"><i class="fa fa-bars"></i>{'Menu Management'|translate}</a></li>
	    <li><a href="{$U_CONFIG_LANGUAGES}"><i class="fa fa-language"></i>{'Languages'|translate}</a></li>
	    <li><a href="{$U_CONFIG_THEMES}"><i class="fa fa-paint-brush"></i>{'Themes'|translate}</a></li>
	</ul>
    </li>
    {if $U_DEV_VERSION}
	<li>
	    <a data-toggle="collapse" href="#child-dev">{'Development'|translate}</a>
	    <ul class="collapse" id="child-dev" data-parent=".accordion">
		{if $U_DEV_API}
		    <li><a href="{$U_DEV_API}">API</a></li>
		{/if}
		<li><a href="{$U_DEV_JS_TESTS}">{'Javascript tests'|translate}</a></li>
	    </ul>
	</li>
    {/if}
</ul>
<script>var menuitem_active = '{$ACTIVE_MENU}';</script>
