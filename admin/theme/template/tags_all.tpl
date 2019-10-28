{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Tags'|translate}</a></li>
    <li class="breadcrumb-item">{'Manage tags'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script>
     var phyxo_msg = phyxo_msg || {};
     phyxo_msg.select_at_least_two_tags = "{'Select at least two tags for merging'|translate}";
    </script>
{/block}

{block name="content"}
    <p>
	<a href="#add-tag" data-toggle="collapse" class="btn btn-submit"><i class="fa fa-plus-circle"></i> {'Add a tag'|translate}</a>
    </p>

    <div id="add-tag" class="collapse">
	<form action="{$F_ACTION_ADD}" method="post">
	    <div class="fieldset">
		<h3>{'Add a tag'|translate}</h3>

		<label>
		    {'New tag'|translate}
		    <input type="text" class="form-control" name="add_tag" size="50">
		</label>

		<p>
		    <input class="btn btn-submit" type="submit" name="add" value="{'Submit'|translate}">
                    <a href="#add-tag" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
	    </div>
	</form>
    </div>

    <form action="{$F_ACTION}" method="post">
	<div class="fieldset" id="tags">
	    <h3>{'Tag selection'|translate}</h3>

	    {foreach $all_tags as $tag}
		<div class="row tag">
		    <div class="col checktag">
			<input type="checkbox" id="tag_id_{$tag.id}" data-name="{$tag.name|escape:'html'}" name="tags[]" value="{$tag.id}">
			<label for="tag_id_{$tag.id}">{$tag.name}</label> ({$tag.counter|translate_dec:'%d photo':'%d photos'})
		    </div>
		    <div class="col">
			{if $tag.U_MANAGE_PHOTOS}
			    <a class="btn btn-sm btn-submit3" href="{$tag.U_MANAGE_PHOTOS}"><i class="fa fa-tasks"></i> {'Manage photos'|translate}</a>
			{/if}
			{if $tag.U_VIEW}
			    <a class="btn btn-sm btn-submit" href="{$tag.U_VIEW}"><i class="fa fa-eye"></i> {'View in gallery'|translate}</a>
			{/if}
			{if !empty($tag.alt_names)}<br>{$tag.alt_names}{/if}
		    </div>
		</div>
	    {/foreach}

	    <p class="checkActions">
		{'Select:'|translate}
		<button type="button" class="btn btn-sm btn-all" id="tagSelectAll">{'All'|translate}</button>
		<button type="button" class="btn btn-sm btn-none" id="tagSelectNone">{'None'|translate}</button>
		<button type="button" class="btn btn-sm btn-invert" id="tagSelectInvert">{'Invert'|translate}</button>
	    </p>
	</div>

	<div class="fieldset" id="actions">
	    <h3>{'Action'|translate}</h3>
	    <div id="no-tag-selected">{'No tags selected, no action possible.'|translate}</div>

	    <p class="d-none" id="selectAction">
		<input type="hidden" name="pwg_token" value="{$csrf_token}">
		<select class="custom-select" name="action">
		    <option value="">{'Choose an action'|translate}</option>
		    <option value="edit">{'Edit selected tags'|translate}</option>
		    <option value="duplicate">{'Duplicate selected tags'|translate}</option>
		    <option value="merge">{'Merge selected tags'|translate}</option>
		    <option value="delete">{'Delete selected tags'|translate}</option>
		</select>
	    </p>

	    <div id="action-edit" class="action d-none">
		<div class="fieldset table-responsive">
		    <h3>{'Edit selected tags'|translate}</h3>
		    <table class="table table-striped table-hovered" style="width:100%">
			<thead>
			    <tr>
				<th>{'Current name'|translate}</th>
				<th>{'New name'|translate}</th>
			    </tr>
			</thead>
			<script type="text/template" class="edit">
			   <%_.each(tags, function(tag, index) { %>
			   <tr>
			     <td><%- tag.name %></td>
			     <td>
			       <input type="text" class="form-control" name="tag_name-<%- tag.id %>" value="<%- tag.name %>" size="50"/>
			     </td>
			   </tr>
			   <% });%>
			</script>
			<tbody id="editHtml">
			</tbody>
		    </table>
		</div>
	    </div>

	    <div id="action-merge" class="action d-none">
		<div class="fieldset">
		    <h3>{'Merge selected tags'|translate}</h3>
		    {'Select the destination tag'|translate}
		    <script type="text/template" class="merge">
			<%_.each(tags, function(tag, index) { %>
			<p>
			    <label>
				<input type="radio" name="destination_tag" value="<%- tag.id %>" <% if (index === 0) { %>checked="checked"<% } %>>
				<%- tag.name %>

				<span class="text-danger <% if (index === 0) { %>d-none<% } %>">
				    {'(this tag will be deleted)'|translate}
				</span>
			    </label>
			</p>
			<% });%>
		    </script>
		    <div id="mergeHtml"></div>
		</div>
	    </div>

	    <div id="action-duplicate" class="action d-none">
		<div class="fieldset table-responsive">
		    <h3>{'Duplicate selected tags'|translate}</h3>
		    <table class="table table-striped table-hovered" style="width:100%">
			<thead>
			    <tr>
				<th>{'Source tag'|translate}</th>
				<th>{'Name of the duplicate'|translate}</th>
			    </tr>
			</thead>
			<script type="text/template" class="duplicate">
			 <%_.each(tags, function(tag, index) { %>
			    <tr>
				<td><%- tag.name %></td>
				<td><input type="text" class="form-control" name="tag_name-<%- tag.id %>" value="<%- tag.name %>" size="50"></td>
			    </tr>
			 <% });%>
			</script>
			<tbody id="duplicateHtml">
			</tbody>
		    </table>
		</div>
	    </div>

	    <p id="action-delete" class="action custom-control custom-checkbox d-none">
		<input class="custom-control-input" type="checkbox" id="confirm-deletion" name="confirm_deletion" value="1">
		<label class="custom-control-label" for="confirm-deletion">{'Are you sure?'|translate}</label>
	    </p>

	    <p id="applyAction" class="d-none">
		<input class="btn btn-submit" type="submit" value="{'Apply action'|translate}" name="submit">
	    </p>
	</div>
    </form>
{/block}
