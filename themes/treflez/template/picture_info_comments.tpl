<!-- comments -->
{if isset($comment_add) || $COMMENT_COUNT > 0}
    <div id="comments">
	{$shortname = $theme_config->comments_disqus_shortname}
	<ul class="nav nav-pills p-2" role="tablist">
	    {if $COMMENT_COUNT > 0}
		<li class="nav-item">
		    <a class="nav-link active" href="#viewcomments" data-toggle="pill" aria-controls="viewcomments">{'number_of_comments'|translate:['count' => $COMMENT_COUNT]}</a>
		</li>
	    {/if}
	    {if isset($comment_add)}
		<li class="nav-item">
		    <a class="nav-link{if $COMMENT_COUNT == 0} active{/if}" href="#addcomment" data-toggle="pill" aria-controls="addcomment">{'Add a comment'|translate}</a>
		</li>
	    {/if}
	</ul>
	<div class="tab-content">
	    {if $COMMENT_COUNT > 0}
		<div id="viewcomments" class="tab-pane active">
		    {include file='comment_list.tpl'}
		    {if !empty($navbar) }
			<div class="row justify-content-center">
			    {include file='navigation_bar.tpl' fragment='comments'}
			</div>
		    {/if}
		</div>
	    {/if}
	    {if isset($comment_add)}
		<div id="addcomment" class="tab-pane{if $COMMENT_COUNT == 0} active{/if}">
		    <form method="post" action="{$comment_add.F_ACTION}">
			{if $comment_add.SHOW_AUTHOR}
			    <div class="form-group">
				<label for="author">{'Author'|translate}{if $comment_add.AUTHOR_MANDATORY} ({'mandatory'|translate}){/if} :</label>
				<input class="form-control" type="text" name="author" id="author" value="{$comment_add.AUTHOR}">
			    </div>
			{/if}
			{if $comment_add.SHOW_EMAIL}
			    <div class="form-group">
				<label for="email">{'Email address'|translate}{if $comment_add.EMAIL_MANDATORY} ({'mandatory'|translate}){/if} :</label>
				<input class="form-control" type="text" name="email" id="email" value="{$comment_add.EMAIL}">
			    </div>
			{/if}
			{if $comment_add.SHOW_WEBSITE}
			    <div class="form-group">
				<label for="website_url">{'Website'|translate} :</label>
				<input class="form-control" type="text" name="website_url" id="website_url" value="{$comment_add.WEBSITE_URL}">
			    </div>
			{/if}
			<div class="form-group">
			    <label for="contentid">{'Comment'|translate} ({'mandatory'|translate}) :</label>
			    <textarea class="form-control" name="content" id="contentid" rows="5" cols="50">{$comment_add.CONTENT}</textarea>
			</div>
			<input type="hidden" name="key" value="{$comment_add.KEY}">
			<button type="submit" class="btn btn-primary btn-raised">{'Submit'|translate}</button>
		    </form>
		</div>
	    {/if}
	</div>
    </div>
{/if}
