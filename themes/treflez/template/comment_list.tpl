<div id="commentList">
    {foreach $comments as $comment}
	<div class="comment">
	    {if isset($comment.src_image)}
		<div class="image">
		    {if isset($comment_derivative_params)}
			{define_derivative name='cropped_derivative_params' width=$comment_derivative_params->sizing->ideal_size[0] height=$comment_derivative_params->sizing->ideal_size[0] crop=true}
		    {else}
			{define_derivative name='cropped_derivative_params' width=$derivative_params->sizing->ideal_size[0] height=$derivative_params->sizing->ideal_size[0] crop=true}
		    {/if}
		    {assign var=derivative value=$pwg->derivative($comment.src_image, $cropped_derivative_params, $image_std_params)}
		    <a href="{$comment.U_PICTURE}">
			<img {if $derivative->is_cached()}src="{$derivative->get_url()}"{else}src="{$ROOT_URL}{$themeconf.icon_dir}/img_small.png" data-src="{$derivative->get_url()}"{/if} alt="{$comment.ALT}">
		    </a>
		</div>
	    {/if}
            <div class="description">
		{if isset($comment.U_DELETE) or isset($comment.U_VALIDATE) or isset($comment.U_EDIT)}
		    <div class="actions">
			{if isset($comment.U_DELETE)}
			    <form method="post" action="{$comment.U_DELETE}" class="form-inline-action">
				<input type="hidden" name="_csrf_token" value="{$csrf_token}">
				<input class="btn btn-danger btn-sm" type="submit" value="{'Delete'|translate}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');">
			    </form>
			{/if}
			{if isset($comment.U_CANCEL)}
			    <a class="btn btn-info btn-sm" href="{$comment.U_CANCEL}">
				{'Cancel'|translate}
			    </a>
			{/if}
			{if isset($comment.U_EDIT) and !isset($comment.IN_EDIT)}
			    <a class="btn btn-primary btn-sm" href="{$comment.U_EDIT}#edit_comment">
				{'Edit'|translate}
			    </a>
			{/if}
			{if isset($comment.U_VALIDATE)}
			    <form method="post" action="{$comment.U_VALIDATE}" class="form-inline-action">
				<input type="hidden" name="_csrf_token" value="{$csrf_token}">
				<input type="submit" class="btn btn-success btn-sm" value="{'Validate'|translate}">
			    </form>
			{/if}
		    </div>
		{/if}

		<span>{if $comment.WEBSITE_URL}<a href="{$comment.WEBSITE_URL}" class="external" target="_blank" rel="nofollow">{$comment.AUTHOR}</a>{else}{$comment.AUTHOR}{/if}</span>
                {if isset($comment.EMAIL)}- <a href="mailto:{$comment.EMAIL}">{$comment.EMAIL}</a>{/if}
                - <span class="commentDate">{$comment.DATE}</span>
		{if isset($comment.IN_EDIT)}
		    <a name="edit_comment"></a>
		    <form method="post" action="{$comment.U_SAVE}">
			<div class="form-group">
			    <label for="website_url">{'Website'|translate} :</label>
			    <input class="form-control" type="text" name="website_url" id="website_url" value="{$comment.WEBSITE_URL}">
			</div>
			<div class="form-group">
			    <label for="contenteditid">{'Edit a comment'|translate} :</label>
			    <textarea class="form-control" name="content" id="contenteditid" rows="5" cols="80">{$comment.CONTENT|@escape}</textarea>
			</div>
			<input type="hidden" name="key" value="{$comment.KEY}">
			<input type="hidden" name="_csrf_token" value="{$csrf_token}">
			<input type="hidden" name="image_id" value="{$comment.IMAGE_ID|default:$current.id}">
			<button type="submit" class="btn btn-primary">{'Submit'|translate}</button>
		    </form>
		{else}
		    <blockquote><div>{$comment.CONTENT}</div></blockquote>
		{/if}
            </div>
	</div>
    {/foreach}
</div>
