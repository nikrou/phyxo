{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'User list'|translate}</a></li>
{/block}

{block name="footer_assets" prepend}
    <script>
     var ws_url = '{$ws}';
     var pwg_token = '{$csrf_token}';
     var phyxo_msg = phyxo_msg || {};
     phyxo_msg.n_users_selected = "{'%d users selected'|translate|escape:javascript}";
     phyxo_msg.no_user_selected = "{'No user selected'|translate|escape:javascript}";
     phyxo_msg.one_user_selected = "{'One user selected'|translate|escape:javascript}";

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

     phyxo_msg.user_password_updated = "{'User password updated'|translate|escape:javascript}";
     phyxo_msg.users_updated = "{'Users modified'|translate}";
     phyxo_msg.new_user_pattern = "{'User "%s" added'|translate|escape:javascript}";
     phyxo_msg.username_changed_pattern = "{'Username has been changed to %s'|translate|escape:javascript}";
     phyxo_msg.user_deleted = "{'User "%s" deleted'|translate|escape:javascript}";
     phyxo_msg.user_infos_updated = "{'User infos updated'|translate|escape:javascript}";
     phyxo_msg.registeredOn_pattern = "{'Registered on %s, %s.'|translate|escape:javascript}";
     phyxo_msg.lastVisit_pattern = "{'Last visit on %s, %s.'|translate|escape:javascript}";
     phyxo_msg.missing_confirm = "{'You need to confirm deletion'|translate|escape:javascript}";
     phyxo_msg.missing_username = "{'Please, enter a login'|translate|escape:javascript}";

     var protectedUsers = [{$protected_users}];
     var guestUser = {$guest_user};

     phyxo_msg.days = "{'%d days'|translate}";
     phyxo_msg.photos_per_page = "{'%d photos per page'|translate}";
     phyxo_msg.user_updated = "{'User %s updated'|translate}";
     phyxo_msg.are_you_sure = "{'Are you sure?'|translate}";

     phyxo_msg.open_user_details = "{'Open user details'|translate}";
     phyxo_msg.close_user_details = "{'Close user details'|translate}";
     phyxo_msg.edit = "{'edit'|translate}";
     phyxo_msg.translate = "{'close'|translate}";

     phyxo_msg.loading = "{'Loading...'|translate}";
     phyxo_msg.show_users = "{'Show %s users'|translate}";
     phyxo_msg.no_matching_user = "{'No matching user found'|translate}";
     phyxo_msg.showing_to_users = "{'Showing %s to %s of %s users'|translate}";
     phyxo_msg.filtered_from_total_users = "{'(filtered from %s total users)'|translate}";
     phyxo_msg.search = "{'Search'|translate}";
     phyxo_msg.first = "{'First'|translate}";
     phyxo_msg.previous = "{'Previous'|translate}";
     phyxo_msg.next = "{'Next'|translate}";
     phyxo_msg.last = "{'Last'|translate}";
     var statusLabels = {
	 {foreach $label_of_status as $status => $label}
	 '{$status}' : '{$label|escape:javascript}',
	 {/foreach}
     };

     var levels = [];
     {foreach $level_options as $id => $level}
     {if $id>0}
     levels[{$id}] = "{$level}";
     {/if}
     {/foreach}
     var groups = [];
     {foreach $association_options as $id => $group}
     {if $id>0}
     groups[{$id}] = "{$group}";
     {/if}
     {/foreach}

     var users_list_config = {
	 pageLength: {$users_list_pageLength},
	 columns: [
	     { data: 'id' },
	     { data: 'username' },
	     { data: 'status' },
	     { data: 'email' },
	     { data: 'groups' },
	     { data: 'level' },
	     { data: 'registration_date' }
	 ],
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
	     aria: {
		 sortAscending:  ": activer pour trier la colonne par ordre croissant",
		 sortDescending: ": activer pour trier la colonne par ordre décroissant"
	     },
	     select: {
		 rows: {
		     _: phyxo_msg.n_users_selected,
		     0: phyxo_msg.no_user_selected,
		     1: phyxo_msg.one_user_selected,
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
    {assign var="users_list_pageLength" value="5"}

    <div class="alert alert-dismissible fade" role="alert">
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
	    <i class="fa fa-times"></i>
	</button>
    </div>

    <p>
	<a href="#add-user" data-toggle="collapse" class="btn btn-submit"><i class="fa fa-plus-circle"></i> {'Add a user'|translate}</a>
    </p>

    <div id="add-user" class="collapse">
	<form id="addUserForm" method="post" name="add_user" action="{$F_ADD_ACTION}">
	    <div class="fieldset">
	        <h3>{'Add a user'|translate}</h3>

		<p>
                    <label>{'Username'|translate}
			<input class="form-control" type="text" name="username" maxlength="50" size="20">
                    </label>
		</p>

		<p>
                    <label>{'Password'|translate}
			<input class="form-control" type="{if $Double_Password}password{else}text{/if}" name="password">
                    </label>
		</p>

		{if $Double_Password}
		    <p>
			<label>{'Confirm Password'|translate}
			    <input class="form-control" type="password" name="password_confirm">
			</label>
		    </p>
		{/if}

		<p>
                    <label>{'Email address'|translate}
			<input class="form-control" type="text" name="email">
                    </label>
		</p>

		<p class="custom-control custom-checkbox">
		    <input class="custom-control-input" type="checkbox" id="send_password_by_mail" name="send_password_by_mail">
		    <label class="custom-control-label" for="send_password_by_mail">{'Send connection settings by email'|translate}</label>
		</p>

		<p>
                    <input class="btn btn-submit" name="submit_add" type="submit" value="{'Submit'|translate}">
                    <a href="#add-user" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
    	    </div>
	</form>
    </div>

    <form method="post" action="{$F_ADD_ACTION}">
	<div class="table-responsive">
	    <table id="users-list" class="table table-striped table-hovered" style="width:100%">
		<thead>
		    <tr>
			<th></th>
			<th>{'Username'|translate}</th>
			<th>{'Status'|translate}</th>
			<th>{'Email address'|translate}</th>
			<th>{'Groups'|translate}</th>
			<th>{'Privacy level'|translate}</th>
			<th>{'registration date'|translate}</th>
		    </tr>
		</thead>
		<tbody>
		</tbody>
	    </table>
	</div>

        <div class="fieldset" id="action">
            <h3>{'Action'|translate}</h3>

            <div id="forbidAction">
		{'No user selected, no action possible.'|translate}
	    </div>
            <div id="permitAction">
		<p>
		    <select class="custom-select" name="selectAction">
			<option value="-1">{'Choose an action'|translate}</option>
			<option disabled="disabled">------------------</option>
			<option value="delete">{'Delete selected users'|translate}</option>
			<option value="status">{'Status'|translate}</option>
			<option value="group_associate">{'associate to group'|translate}</option>
			<option value="group_dissociate">{'dissociate from group'|translate}</option>
			<option value="enabled_high">{'High definition enabled'|translate}</option>
			<option value="level">{'Privacy level'|translate}</option>
			<option value="nb_image_page">{'Number of photos per page'|translate}</option>
			<option value="theme">{'Theme'|translate}</option>
			<option value="language">{'Language'|translate}</option>
			<option value="recent_period">{'Recent period'|translate}</option>
			<option value="expand">{'Expand all albums'|translate}</option>
			{if $ACTIVATE_COMMENTS}
			    <option value="show_nb_comments">{'Show number of comments'|translate}</option>
			{/if}
			<option value="show_nb_hits">{'Show number of hits'|translate}</option>
		    </select>
		</p>

		{* delete *}
		<div id="action_delete">
                    <p class="custom-control custom-checkbox">
			<input class="custom-control-input" type="checkbox" id="confirm_deletion" name="confirm_deletion" value="1">
			<label class="custom-control-label" for="confirm_deletion">{'Are you sure?'|translate}</label>
		    </p>
		</div>

		{* status *}
		<div id="action_status">
		    <p>
			<select class="custom-select" name="status">
			    {html_options options=$pref_status_options selected=$pref_status_selected}
			</select>
		    </p>
		</div>

		{* group_associate *}
		<div id="action_group_associate">
		    <p>
			<select class="custom-select" name="associate">
			    {html_options options=$association_options selected=$associate_selected|default:null}
			</select>
		    </p>
		</div>

		{* group_dissociate *}
		<div id="action_group_dissociate">
		    <p>
			<select class="custom-select" name="dissociate">
			    {html_options options=$association_options selected=$dissociate_selected|default:null}
			</select>
		    </p>
		</div>

		{* enabled_high *}
		<div id="action_enabled_high">
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="enabled_high_true" name="enabled_high" value="true">
			<label class="custom-control-label" for="enabled_high_true">{'Yes'|translate}</label>
		    </p>
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="enabled_high_false" name="enabled_high" value="false" checked="checked">
			<label class="custom-control-label" for="enabled_high_false">{'No'|translate}</label>
		    </p>
		</div>

		{* level *}
		<div id="action_level">
		    <p>
			<select class="custom-select" name="level" size="1">
			    {html_options options=$level_options selected=$level_selected}
			</select>
		    </p>
		</div>

		{* nb_image_page *}
		<div id="action_nb_image_page">
		    <p>
			<label class="nb_image_page_infos"></label>
			<input type="text" class="form-control" name="nb_image_page" value="{$NB_IMAGE_PAGE}">
		    </p>
		</div>

		{* theme *}
		<div id="action_theme">
		    <p>
			<select class="custom-select" name="theme" size="1">
			    {html_options options=$theme_options selected=$theme_selected}
			</select>
		    </p>
		</div>

		{* language *}
		<div id="action_language">
		    <p>
			<select class="custom-select" name="language" size="1">
			    {html_options options=$language_options selected=$language_selected}
			</select>
		    </p>
		</div>

		{* recent_period *}
		<div id="action_recent_period">
		    <p>
			<label class="recent_period_infos"></label>
			<input type="text" class="form-control" name="recent_period" value="{$RECENT_PERIOD}">
		    </p>
		</div>

		{* expand *}
		<div id="action_expand">
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="expand_true" name="expand" value="true">
			<label class="custom-control-label" for="expand_true">{'Yes'|translate}</label>
		    </p>
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="expand_false" name="expand" value="false" checked="checked">
			<label class="custom-control-label" for="expand_false">{'No'|translate}</label>
		    </p>
		</div>

		{* show_nb_comments *}
		<div id="action_show_nb_comments">
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="show_nb_comments_true" name="show_nb_comments" value="true">
			<label class="custom-control-label" for="show_nb_comments_true">{'Yes'|translate}</label>
		    </p>
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="show_nb_comments_false" name="show_nb_comments" value="false" checked="checked">
			<label class="custom-control-label" for="show_nb_comments_false">{'No'|translate}</label>
		    </p>
		</div>

		{* show_nb_hits *}
		<div id="action_show_nb_hits">
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="show_nb_hits_true" name="show_nb_hits" value="true">
			<label class="custom-control-label" for="show_nb_hits_true">{'Yes'|translate}</label>
		    </p>
		    <p class="custom-control custom-radio">
			<input class="custom-control-input" type="radio" id="show_nb_hits_false" name="show_nb_hits" value="false" checked="checked">
			<label class="custom-control-label" for="show_nb_hits_false">{'No'|translate}</label>
		    </p>
		</div>

		<p id="applyActionBlock" class="actionButtons">
		    <input id="applyAction" class="btn btn-submit" type="submit" value="{'Apply action'|translate}" name="submit">
		    <span id="applyOnDetails"></span>
		</p>
            </div> {* #permitAction *}
        </div>
    </form>

    {* Underscore Template Definition *}
    <script type="text/template" class="userDetails">
     <form id="user<%- user.id %>">
       <input type="hidden" name="user_id" value="<%- user.id %>">
       <div class="fieldset">
	 <h3>{'User'|translate} <%- user.username %></h3>
         <div id="userActions">
	   <p>
	     <% if (!user.isGuest) { %>
	     <a class="btn btn-sm btn-success" href="#changePassword" data-toggle="collapse"><i class="fa fa-key"></i> {'Change password'|translate}</a>
	     <% } %>
	     <a class="btn btn-sm btn-secondary" href="<%- '{$F_USER_PERM}'.replace({$F_USER_PERM_DUMMY_USER}, user.id) %>"><i class="fa fa-lock"></i> {'Permissions'|translate}</a>
	     <% if (!user.isProtected) { %>
	     <button type="button" id="user-delete" class="btn btn-sm btn-danger" data-username="<%- user.username %>" data-user_id="<%- user.id %>"><i class="fa fa-trash"></i>{'Delete'|translate}</button>
	     <% } %>
	     <% if (!user.isGuest) { %>
	     <a class="btn btn-sm btn-info" href="#changeUsername" data-toggle="collapse"><i class="fa fa-pencil"></i> {'Change username'|translate}</a>
	     <% } %>
	   </p>

	   <% if (!user.isGuest) { %>
	   <div id="changePassword" class="collapse" data-parent="#userActions">
	     <h4>{'New password'|translate}</h4>
	     <p>
	       <input class="form-control" type="text">
	     </p>
	     <p>
               <button class="btn btn-submit" type="submit">{'Submit'|translate}</button>
               <a href="#changePassword" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
	     </p>
	   </div>

	   <div id="changeUsername" class="collapse" data-parent="#userActions">
	     <h4>{'New username'|translate}</h4>
	     <p>
               <input type="text" class="form-control">
	     </p>
	     <p>
               <button class="btn btn-submit" type="submit">{'Submit'|translate}</button>
	       <a href="#changeUsername" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
	     </p>
	   </div>
	   <% } %>
	 </div>

	 <div class="userStats"><%- user.registeredOn_string %><br><%- user.lastVisit_string %></div>
       </div>

       <div class="fieldset user-infos">
           <h3>{'Properties'|translate}</h3>
           <div class="userProperty">
             <label>{'Email address'|translate}
	       <% if (!user.isGuest) { %>
               <input class="form-control" name="email" type="text" value="<%- user.email %>">
	       <% } else { %>
	       {'N/A'|translate}
	       <% } %>
             </label>
	   </div>

	   <div class="userProperty">
             <label>{'Status'|translate}
	       <% if (!user.isProtected) { %>
	       <select class="custom-select" name="status">
		 <% _.each( user.statusOptions, function( option ){ %>
		 <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
		 <% }); %>
	       </select>
	       <% } else { %>
	       <%- user.statusLabel %>
	       <% } %>
             </label>
	   </div>

	   <div class="userProperty">
             <label>{'Privacy level'|translate}
	       <select class="custom-select" name="level">
		 <% _.each( user.levelOptions, function( option ){ %>
		 <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
		 <% }); %>
	       </select>
             </label>
	   </div>

	   <div class="custom-control custom-checkbox">
	       <input type="checkbox" class="custom-control-input" id="enabled_high" name="enabled_high"<% if (user.enabled_high === true) { %> checked="checked"<% } %>>
               <label class="custom-control-label" for="enabled_high">{'High definition enabled'|translate}
	   </div>

	   <div class="userProperty">
             <label>{'Groups'|translate}
	       <select data-selectize="groups" placeholder="{'Type in a search term'|translate}" name="group_id[]" multiple></select>
             </label>
	   </div>

	   <h3>{'Preferences'|translate}</h3>

	   <div class="userProperty slider">
	     <p>
               <label class="nb_image_page_infos" for="nb_image_page"></label>
	       <input type="text" class="form-control" id="nb_image_page" name="nb_image_page" value="<%- user.nb_image_page %>">
	     </p>
	   </div>

	   <div class="userProperty">
             <label>{'Theme'|translate}
	       <select class="custom-select" name="theme">
		 <% _.each( user.themeOptions, function( option ){ %>
		 <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
		 <% }); %>
	       </select>
             </label>
	   </div>

	   <div class="userProperty">
             <label>{'Language'|translate}
	       <select class="custom-select" name="language">
		 <% _.each( user.languageOptions, function( option ){ %>
		 <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
		 <% }); %>
	       </select>
             </label>
	   </div>

	   <div class="userProperty slider">
	     <p>
	       <label>{'Recent period'|translate}: <span class="recent_period_infos"></span></label>
	       <input type="text" class="form-control" name="recent_period" value="<%- user.recent_period %>">
	     </p>
	   </div>

	   <div class="custom-control custom-checkbox">
	       <input class="custom-control-input" type="checkbox" id="expand" name="expand"<% if (user.expand === true) { %> checked="checked"<% }%>>
               <label class="custom-control-label" for="expand">{'Expand all albums'|translate}</label>
	   </div>

	   <div class="custom-control custom-checkbox">
	       <input class="custom-control-input" type="checkbox" id="show_nb_comments" name="show_nb_comments"<% if (user.show_nb_comments === true) { %> checked="checked"<% }%>>
               <label class="custom-control-label" for="show_nb_comments">{'Show number of comments'|translate}</label>
	   </div>

	   <div class="custom-control custom-checkbox">
	       <input class="custom-control-input" type="checkbox" id="show_nb_hits" name="show_nb_hits"<% if (user.show_nb_hits === true) { %> checked="checked"<% }%>>
               <label class="custom-control-label" for="show_nb_hits">{'Show number of hits'|translate}</label>
	   </div>

	   <p>
	     <input class="btn btn-submit" type="submit" value="{'Update user'|translate|escape:html}" data-user_id="<%- user.id %>">
	   </p>
       </div>
     </form>
    </script>
{/block}
