{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Configuration'|translate}</a></li>
    <li class="breadcrumb-item">{'Comments'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='common' load='footer' path='admin/theme/js/common.js'}

    <form method="post" action="{$F_ACTION}" class="properties">
	<div class="fieldset">
	    <p>
		<label class="font-checkbox">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="activate_comments" id="activate_comments"{if ($comments.activate_comments)} checked="checked"{/if} data-toggle="collapse" data-target="#comments-params">
		    {'Activate comments'|translate}
		</label>
	    </p>

	    <div id="comments-params" class="collapse{if ($comments.activate_comments)} show{/if}">
		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="comments_forall" {if ($comments.comments_forall)}checked="checked"{/if}>
			{'Comments for all'|translate}
		    </label>
		</p>

		<p>
		    <label>
			{'Number of comments per page'|translate}
			<input type="text" size="3" maxlength="4" name="nb_comment_page" id="nb_comment_page" value="{$comments.NB_COMMENTS_PAGE}">
		    </label>
		</p>

		<p>
		    <label>
			{'Default comments order'|translate}
			<select name="comments_order">
			    {html_options options=$comments.comments_order_options selected=$comments.comments_order}
			</select>
		    </label>
		</p>

		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="comments_validation" {if ($comments.comments_validation)}checked="checked"{/if} data-toggle="collapse" data-target="#admin-comment-validation">
			{'Validation'|translate}
		    </label>
		</p>

		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="comments_author_mandatory" {if ($comments.comments_author_mandatory)}checked="checked"{/if}>
			{'Username is mandatory'|translate}
		    </label>
		</p>

		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="comments_email_mandatory" {if ($comments.comments_email_mandatory)}checked="checked"{/if}>
			{'Email address is mandatory'|translate}
		    </label>
		</p>

		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="comments_enable_website" {if ($comments.comments_enable_website)}checked="checked"{/if}>
			{'Allow users to add a link to their website'|translate}
		    </label>
		</p>

		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="user_can_edit_comment" {if ($comments.user_can_edit_comment)}checked="checked"{/if} data-toggle="collapse" data-target="#admin-edit-comment">
			{'Allow users to edit their own comments'|translate}
		    </label>
		</p>

		<p>
		    <label class="font-checkbox">
			<i class="fa fa-check-square"></i>
			<input type="checkbox" name="user_can_delete_comment" {if ($comments.user_can_delete_comment)}checked="checked"{/if} data-toggle="collapse" data-target="#admin-delete-comment">
			{'Allow users to delete their own comments'|translate}
		    </label>
		</p>

		<p>{'Notify administrators when a comment is'|translate}</p>
		<ul>
		    <li id="admin-comment-validation" class="form-check-inline collapse{if ($comments.comments_validation)} show{/if}">
			<label id="admin-comment-validation" class="font-checkbox">
			    <i class="fa fa-check-square"></i>
			    <input type="checkbox" name="email_admin_on_comment_validation" {if ($comments.email_admin_on_comment_validation)}checked="checked"{/if}>
			    {'pending validation'|translate}
			</label>
		    </li>

		    <li>
			<label class="font-checkbox">
			    <i class="fa fa-check-square"></i>
			    <input type="checkbox" name="email_admin_on_comment" {if ($comments.email_admin_on_comment)}checked="checked"{/if}>
			    {'added'|translate}
			</label>
		    </li>

		    <li id="admin-edit-comment" class="collapse{if ($comments.user_can_edit_comment)} show{/if}">
			<label id="admin-edit-comment" class="font-checkbox">
			    <i class="fa fa-check-square"></i>
			    <input type="checkbox" name="email_admin_on_comment_edition" {if ($comments.email_admin_on_comment_edition)}checked="checked"{/if}>
			    {'modified'|translate}
			</label>
		    </li>

		    <li id="admin-delete-comment" class="collapse{if ($comments.user_can_delete_comment)} show{/if}">
			<label class="font-checkbox">
			    <i class="fa fa-check-square"></i>
			    <input type="checkbox" name="email_admin_on_comment_deletion" {if ($comments.email_admin_on_comment_deletion)}checked="checked"{/if}>
			    {'deleted'|translate}
			</label>
		    </li>
		</ul>
	    </div
	</div>

	<p>
	    <input type="submit" name="submit" value="{'Save Settings'|translate}" class="btn btn-submit">
	</p>

    </form>
{/block}
