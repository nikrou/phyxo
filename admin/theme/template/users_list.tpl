{combine_script id="common" load="footer" path="admin/theme/js/common.js"}

{combine_script id="jquery.dataTables" load="footer" path="admin/theme/js/plugins/jquery.dataTables.js"}
{combine_css path="admin/theme/js/plugins/datatables/css/jquery.dataTables.css"}

{combine_script id="jquery.selectize" load="footer" path="admin/theme/js/plugins/selectize.js"}
{combine_css id="jquery.selectize" path="admin/theme/js/plugins/selectize.clear.css"}

{combine_script id="underscore" load="footer" path="admin/theme/js/plugins/underscore.js"}

{combine_script id="jquery.ui.slider" require="jquery.ui" load="footer" path="admin/theme/js/ui/jquery.ui.slider.js"}
{combine_css path="admin/theme/js/ui/theme/jquery.ui.slider.css"}

{html_head}
<script type="text/javascript">
var selectedMessage_pattern = "{'%d of %d users selected'|translate|escape:javascript}";
var selectedMessage_none = "{'No user selected of %d users'|translate|escape:javascript}";
var selectedMessage_all = "{'All %d users are selected'|translate|escape:javascript}";
var applyOnDetails_pattern = "{'on the %d selected users'|translate|escape:javascript}";
var newUser_pattern = "&#x2714; {'User %s added'|translate|escape:javascript}";
var registeredOn_pattern = "{'Registered on %s, %s.'|translate|escape:javascript}";
var lastVisit_pattern = "{'Last visit on %s, %s.'|translate|escape:javascript}";
var missingConfirm = "{'You need to confirm deletion'|translate|escape:javascript}";
var missingUsername = "{'Please, enter a login'|translate|escape:javascript}";

var allUsers = [{$all_users}];
var selection = [{$selection}];
var pwg_token = "{$PWG_TOKEN}";

var protectedUsers = [{$protected_users}];
var guestUser = {$guest_user};

var truefalse = {
  'true':"{'Yes'|translate}",
  'false':"{'No'|translate}",
};

var phyxo_msg = phyxo_msg || {};
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
{foreach from=$label_of_status key=status item=label}
  '{$status}' : '{$label|escape:javascript}',
{/foreach}
};
</script>
{/html_head}

{combine_script id="user_list" load="footer" path="admin/theme/js/user_list.js"}

{html_style}{literal}
.dataTables_wrapper, .dataTables_info {clear:none;}
table.dataTable {clear:right;padding-top:10px;}
.dataTable td img {margin-bottom: -6px;margin-left: -6px;}
.paginate_enabled_previous, .paginate_enabled_previous:hover, .paginate_disabled_previous, .paginate_enabled_next, .paginate_enabled_next:hover, .paginate_disabled_next {background:none;}
.paginate_enabled_previous, .paginate_enabled_next {color:#005E89 !important;}
.paginate_enabled_previous:hover, .paginate_enabled_next:hover {color:#D54E21 !important; text-decoration:underline !important;}

.paginate_disabled_next, .paginate_enabled_next {padding-right:3px;}
.bulkAction {margin-top:10px;}
#addUserForm p {margin-left:0;}
#applyActionBlock .actionButtons {margin-left:0;}
span.infos, span.errors {background-image:none; padding:2px 5px; margin:0;border-radius:5px;}

.userStats {margin-top:10px;}
.recent_period_infos {margin-left:10px;}
.nb_image_page, .recent_period {width:340px;margin-top:5px;}
#action_recent_period .recent_period {display:inline-block;}
{/literal}{/html_style}

<div class="titrePage">
  <h2>{'User list'|translate}</h2>
</div>

<p class="showCreateAlbum" id="showAddUser">
  <a href="#" id="addUser" class="icon-plus-circled">{'Add a user'|translate}</a>
  <span class="infos" style="display:none"></span>
</p>

<form id="addUserForm" style="display:none" method="post" name="add_user" action="{$F_ADD_ACTION}">
  <fieldset>
    <legend>{'Add a user'|translate}</legend>

    <p>
      <strong>{'Username'|translate}</strong><br>
      <input type="text" name="username" maxlength="50" size="20">
    </p>

    <p>
      <strong>{'Password'|translate}</strong><br>
      <input type="{if $Double_Password}password{else}text{/if}" name="password">
    </p>

{if $Double_Password}
    <p>
      <strong>{'Confirm Password'|translate}</strong><br>
      <input type="password" name="password_confirm">
    </p>
{/if}

    <p>
      <strong>{'Email address'|translate}</strong><br>
      <input type="text" name="email">
    </p>

    <p>
      <label><input type="checkbox" name="send_password_by_mail"> <strong>{'Send connection settings by email'|translate}</strong></label>
    </p>

    <p class="actionButtons">
      <input class="submit" name="submit_add" type="submit" value="{'Submit'|translate}">
      <a href="#" id="addUserClose">{'Cancel'|translate}</a>
      <span class="loading" style="display:none"><img src="./theme/images/ajax-loader-small.gif" alt=""></span>
      <span class="errors" style="display:none"></span>
    </p>
  </fieldset>
</form>

<form method="post" name="preferences" action="">

<table id="userList">
  <thead>
    <tr>
      <th>id</th>
      <th>{'Username'|translate}</th>
      <th>{'Status'|translate}</th>
      <th>{'Email address'|translate}</th>
      <th>{'Groups'|translate}</th>
      <th>{'Privacy level'|translate}</th>
      <th>{'registration date'|translate}</th>
    </tr>
  </thead>
</table>

<div style="clear:right"></div>

<p class="checkActions">
  {'Select:'|translate}
  <a href="#" id="selectAll">{'All'|translate}</a>,
  <a href="#" id="selectNone">{'None'|translate}</a>,
  <a href="#" id="selectInvert">{'Invert'|translate}</a>

  <span id="selectedMessage"></span>
</p>

<fieldset id="action">
  <legend>{'Action'|translate}</legend>

  <div id="forbidAction"{if count($selection) != 0} style="display:none"{/if}>{'No user selected, no action possible.'|translate}</div>
  <div id="permitAction"{if count($selection) == 0} style="display:none"{/if}>

    <select name="selectAction">
      <option value="-1">{'Choose an action'|translate}</option>
      <option disabled="disabled">------------------</option>
      <option value="delete" class="icon-trash">{'Delete selected users'|translate}</option>
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

    {* delete *}
    <div id="action_delete" class="bulkAction">
      <p><label><input type="checkbox" name="confirm_deletion" value="1"> {'Are you sure?'|translate}</label></p>
    </div>

    {* status *}
    <div id="action_status" class="bulkAction">
      <select name="status">
        {html_options options=$pref_status_options selected=$pref_status_selected}
      </select>
    </div>

    {* group_associate *}
    <div id="action_group_associate" class="bulkAction">
      {html_options name=associate options=$association_options selected=$associate_selected}
    </div>

    {* group_dissociate *}
    <div id="action_group_dissociate" class="bulkAction">
      {html_options name=dissociate options=$association_options selected=$dissociate_selected}
    </div>

    {* enabled_high *}
    <div id="action_enabled_high" class="bulkAction">
      <label><input type="radio" name="enabled_high" value="true">{'Yes'|translate}</label>
      <label><input type="radio" name="enabled_high" value="false" checked="checked">{'No'|translate}</label>
    </div>

    {* level *}
    <div id="action_level" class="bulkAction">
      <select name="level" size="1">
        {html_options options=$level_options selected=$level_selected}
      </select>
    </div>

    {* nb_image_page *}
    <div id="action_nb_image_page" class="bulkAction">
      <strong class="nb_image_page_infos"></strong>
      <div class="nb_image_page"></div>
      <input type="hidden" name="nb_image_page" value="{$NB_IMAGE_PAGE}">
    </div>

    {* theme *}
    <div id="action_theme" class="bulkAction">
      <select name="theme" size="1">
        {html_options options=$theme_options selected=$theme_selected}
      </select>
    </div>

    {* language *}
    <div id="action_language" class="bulkAction">
      <select name="language" size="1">
        {html_options options=$language_options selected=$language_selected}
      </select>
    </div>

    {* recent_period *}
    <div id="action_recent_period" class="bulkAction">
      <div class="recent_period"></div>
      <span class="recent_period_infos"></span>
      <input type="hidden" name="recent_period" value="{$RECENT_PERIOD}">
    </div>

    {* expand *}
    <div id="action_expand" class="bulkAction">
      <label><input type="radio" name="expand" value="true">{'Yes'|translate}</label>
      <label><input type="radio" name="expand" value="false" checked="checked">{'No'|translate}</label>
    </div>

    {* show_nb_comments *}
    <div id="action_show_nb_comments" class="bulkAction">
      <label><input type="radio" name="show_nb_comments" value="true">{'Yes'|translate}</label>
      <label><input type="radio" name="show_nb_comments" value="false" checked="checked">{'No'|translate}</label>
    </div>

    {* show_nb_hits *}
    <div id="action_show_nb_hits" class="bulkAction">
      <label><input type="radio" name="show_nb_hits" value="true">{'Yes'|translate}</label>
      <label><input type="radio" name="show_nb_hits" value="false" checked="checked">{'No'|translate}</label>
    </div>

    <p id="applyActionBlock" style="display:none" class="actionButtons">
      <input id="applyAction" class="submit" type="submit" value="{'Apply action'|translate}" name="submit"> <span id="applyOnDetails"></span>
      <span id="applyActionLoading" style="display:none"><img src="./theme/images/ajax-loader-small.gif" alt=""></span>
      <span class="infos" style="display:none">&#x2714; {'Users modified'|translate}</span>
    </p>

  </div> {* #permitAction *}
</fieldset>

</form>

{* Underscore Template Definition *}
<script type="text/template" class="userDetails">
<form>
  <div class="userActions">
<% if (!user.isGuest) { %>
    <span class="changePasswordDone infos" style="display:none">&#x2714; {'Password updated'|translate}</span>
    <span class="changePassword" style="display:none">{'New password'|translate} <input type="text"> <a href="#" class="buttonLike updatePassword"><img src="./theme/images/ajax-loader-small.gif" alt="" style="margin-bottom:-1px;margin-left:1px;display:none;"><span class="text">{'Submit'|translate}</span></a> <a href="#" class="cancel">{'Cancel'|translate}</a></span>
    <a class="icon-key changePasswordOpen" href="#">{'Change password'|translate}</a>
    <br>
<% } %>

    <a target="_blank" href="./index.php?page=user_perm&amp;user_id=<%- user.id %>" class="icon-lock">{'Permissions'|translate}</a>

<% if (!user.isProtected) { %>
    <br><span class="userDelete"><img class="loading" src="./theme/images/ajax-loader-small.gif" alt="" style="display:none;"><a href="#" class="icon-trash" data-user_id="<%- user.id %>">{'Delete'|translate}</a></span>
<% } %>

  </div>

  <span class="changeUsernameOpen"><strong class="username"><%- user.username %></strong>

<% if (!user.isGuest) { %>
  <a href="#" class="icon-pencil">{'Change username'|translate}</a></span>
  <span class="changeUsername" style="display:none">
  <input type="text"> <a href="#" class="buttonLike updateUsername"><img src="./theme/images/ajax-loader-small.gif" alt="" style="margin-bottom:-1px;margin-left:1px;display:none;"><span class="text">{'Submit'|translate}</span></a> <a href="#" class="cancel">{'Cancel'|translate}</a>
<% } %>

  </span>

  <div class="userStats"><%- user.registeredOn_string %><br><%- user.lastVisit_string %></div>

  <div class="userPropertiesContainer">
    <input type="hidden" name="user_id" value="<%- user.id %>">
    <div class="userPropertiesSet">
      <div class="userPropertiesSetTitle">{'Properties'|translate}</div>

      <div class="userProperty"><strong>{'Email address'|translate}</strong>
        <br>
<% if (!user.isGuest) { %>
        <input name="email" type="text" value="<%- user.email %>">
<% } else { %>
      {'N/A'|translate}
<% } %>
      </div>

      <div class="userProperty"><strong>{'Status'|translate}</strong>
        <br>
<% if (!user.isProtected) { %>
        <select name="status">
  <% _.each( user.statusOptions, function( option ){ %>
          <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
  <% }); %>
        </select>
<% } else { %>
        <%- user.statusLabel %>
<% } %>
      </div>

      <div class="userProperty"><strong>{'Privacy level'|translate}</strong>
        <br>
        <select name="level">
<% _.each( user.levelOptions, function( option ){ %>
          <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
<% }); %>
        </select>
      </div>

      <div class="userProperty"><label><input type="checkbox" name="enabled_high"<% if (user.enabled_high == 'true') { %> checked="checked"<% } %>> <strong>{'High definition enabled'|translate}</strong></label></div>

      <div class="userProperty"><strong>{'Groups'|translate}</strong><br>
        <select data-selectize="groups" placeholder="{'Type in a search term'|translate}"
          name="group_id[]" multiple style="width:340px;"></select>
      </div>
    </div>

    <div class="userPropertiesSet userPrefs">
      <div class="userPropertiesSetTitle">{'Preferences'|translate}</div>

      <div class="userProperty"><strong class="nb_image_page_infos"></strong>
        <div class="nb_image_page"></div>
        <input type="hidden" name="nb_image_page" value="<%- user.nb_image_page %>">
      </div>

      <div class="userProperty"><strong>{'Theme'|translate}</strong><br>
        <select name="theme">
<% _.each( user.themeOptions, function( option ){ %>
          <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
<% }); %>
        </select>
      </div>

      <div class="userProperty"><strong>{'Language'|translate}</strong><br>
        <select name="language">
<% _.each( user.languageOptions, function( option ){ %>
          <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
<% }); %>
        </select>
      </div>

      <div class="userProperty"><strong>{'Recent period'|translate}</strong> <span class="recent_period_infos"></span>
        <div class="recent_period"></div>
        <input type="hidden" name="recent_period" value="<%- user.recent_period %>">
      </div>

      <div class="userProperty"><label><input type="checkbox" name="expand"<% if (user.expand == 'true') { %> checked="checked"<% }%>> <strong>{'Expand all albums'|translate}</strong></label></div>

      <div class="userProperty"><label><input type="checkbox" name="show_nb_comments"<% if (user.show_nb_comments == 'true') { %> checked="checked"<% }%>> <strong>{'Show number of comments'|translate}</strong></label></div>

      <div class="userProperty"><label><input type="checkbox" name="show_nb_hits"<% if (user.show_nb_hits == 'true') { %> checked="checked"<% }%>> <strong>{'Show number of hits'|translate}</strong></label></div>

    </div>

    <div style="clear:both"></div>
  </div> {* userPropertiesContainer *}

  <span class="infos propertiesUpdateDone" style="display:none">&#x2714; <%- user.updateString %></span>

  <input type="submit" value="{'Update user'|translate|escape:html}" style="display:none;" data-user_id="<%- user.id %>">
  <img class="submitWait" src="./theme/images/ajax-loader-small.gif" alt="" style="display:none">
</form>
</script>
