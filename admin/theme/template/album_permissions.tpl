{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album'|translate}:
	{foreach $CATEGORIES_NAV as $category_nav}
	    <a href="{$category_nav.url}">{$category_nav.name}</a>
	    {if !$category_nav@last}/{/if}
	{/foreach}
    </li>
    <li class="breadcrumb-item">{'Permissions'|translate}</li>
{/block}

{block name="head_assets" append}
    <link rel="stylesheets" href="{$ROOT_URL}admin/theme/js/plugins/selectize.clear.css"/>
{/block}

{block name="footer_assets" prepend}
    <script src="{$ROOT_URL}admin/theme/js/LocalStorageCache.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/plugins/selectize.js"></script>
    <script>
     (function(){
	 {* <!-- GROUPS --> *}
	 var groupsCache = new GroupsCache({
	     serverKey: '{$CACHE_KEYS.groups}',
	     serverId: '{$CACHE_KEYS._hash}',
	     rootUrl: '{$ROOT_URL}'
	 });

	 groupsCache.selectize(jQuery('[data-selectize=groups]'));

	 {* <!-- USERS --> *}
	 var usersCache = new UsersCache({
	     serverKey: '{$CACHE_KEYS.users}',
	     serverId: '{$CACHE_KEYS._hash}',
	     rootUrl: '{$ROOT_URL}'
	 });

	 usersCache.selectize($('[data-selectize=users]'));

	 {* <!-- TOGGLES --> *}
	 function checkStatusOptions() {
	     if ($("input[name=status]:checked").val() == "private") {
		 $("#privateOptions, #applytoSubAction").show();
	     }
	     else {
		 $("#privateOptions, #applytoSubAction").hide();
	     }
	 }

	 checkStatusOptions();
	 $("#selectStatus").change(function() {
	     checkStatusOptions();
	 });
     }());
    </script>
{/block}

{block name="content"}
    <form action="{$F_ACTION}" method="post" id="categoryPermissions">
	<div class="fieldset">
	    <h3>{'Access type'|translate}</h3>

	    <p>
		<label><input type="radio" name="status" value="public" {if not $private}checked="checked"{/if}> <strong>{'public'|translate}</strong> : <em>{'any visitor can see this album'|translate}</em></label>
	    </p>
	    <p>
		<label><input type="radio" name="status" value="private" {if $private}checked="checked"{/if}> <strong>{'private'|translate}</strong> : <em>{'visitors need to login and have the appropriate permissions to see this album'|translate}</em></label>
	    </p>
	</div>

	<div class="fieldset">
	    <h3>{'Groups and users'|translate}</h3>

	    <p>
		{if count($groups) > 0}
		    <label for="groups">{'Permission granted for groups'|translate}</label>
		    <select id="groups" data-selectize="groups" data-value="{$groups_selected|json_encode|escape:html}"
				placeholder="{'Type in a search term'|translate}"
				name="groups[]" multiple></select>
		{else}
		    {'There is no group in this gallery.'|translate} <a href="{$U_GROUPS}">{'Group management'|translate}</a>
		{/if}
	    </p>

	    <p>
		<label for="users">{'Permission granted for users'|translate}</label>
		<select id="users" data-selectize="users" data-value="{$users_selected|json_encode|escape:html}"
			    placeholder="{'Type in a search term'|translate}"
			    name="users[]" multiple></select>
	    </p>

	    {if isset($nb_users_granted_indirect) && $nb_users_granted_indirect>0}
		<p>
		    {'%u users have automatic permission because they belong to a granted group.'|translate:$nb_users_granted_indirect}
		    <a class="btn btn-submit" href="#indirectPermissionsDetails" data-toggle="collapse">{'show/hide details'|translate}</a>
		</p>

		<ul id="indirectPermissionsDetails" class="collapse">
		    {foreach $user_granted_indirect_groups as $group_details}
			<li><strong>{$group_details.group_name}</strong> : {$group_details.group_users}</li>
		    {/foreach}
		</ul>
	    {/if}
	</div>

	<p>
	    <input class="btn btn-submit" type="submit" value="{'Save Settings'|translate}" name="submit">
	    <label id="applytoSubAction" class="visually-hidden">
		<input type="checkbox" name="apply_on_sub" {if $INHERIT}checked="checked"{/if}>
		{'Apply to sub-albums'|translate}
	    </label>
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	</p>
    </form>
{/block}
