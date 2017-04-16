{extends file="index.tpl"}

{footer_script}
var user_tags = user_tags || {};
user_tags.allow_delete = {$TAGS_PERMISSION_DELETE};
user_tags.allow_creation = {$TAGS_PERMISSION_ALLOW_CREATION};
user_tags.ws_getList = '{$USER_TAGS_WS_GETLIST}';
user_tags.tags_updated = '{"Tags updated"|translate}';
{/footer_script}

{block name="menubar"}{/block}

{block name="breadcrumb"}
    <span class="section-title">{$SECTION_TITLE}</span>
    <span class="separator">{$LEVEL_SEPARATOR}</span>
    <span class="title">{$current.TITLE}</span>
{/block}

{block name="content"}
    {if !empty($PLUGIN_PICTURE_BEFORE)}{$PLUGIN_PICTURE_BEFORE}{/if}

    <div id="main-image">
	<div class="wrapper-image">
	    <img src="{$current.selected_derivative->get_url()}" alt="{$ALT_IMG}" title="{if isset($COMMENT_IMG)}{$COMMENT_IMG|strip_tags:false|replace:'"':' '}{else}{$current.TITLE_ESC} - {$ALT_IMG}{/if}">
	</div>

	{if isset($COMMENT_IMG)}
	    <p class="image-comment">{$COMMENT_IMG}</p>
	{/if}
    </div>

    {if !empty($COMMENT_COUNT)}
	<div id="comments" {if (!isset($comment_add) && ($COMMENT_COUNT == 0))}class="noCommentContent"{else}class="commentContent"{/if}>
	    <div id="commentsSwitcher"></div>
	    <h3>{$COMMENT_COUNT|translate_dec:'%d comment':'%d comments'}</h3>

	    <div id="pictureComments">
		{if isset($comment_add)}
		    <div id="comment-add">
			<h4>{'Add a comment'|translate}</h4>
			<form method="post" action="{$comment_add.F_ACTION}" id="addComment">
			    {if $comment_add.SHOW_AUTHOR}
				<p><label for="author">{'Author'|translate}{if $comment_add.AUTHOR_MANDATORY} ({'mandatory'|translate}){/if} :</label></p>
				<p><input type="text" name="author" id="author" value="{$comment_add.AUTHOR}"></p>
			    {/if}
			    {if $comment_add.SHOW_EMAIL}
				<p><label for="email">{'Email address'|translate}{if $comment_add.EMAIL_MANDATORY} ({'mandatory'|translate}){/if} :</label></p>
				<p><input type="text" name="email" id="email" value="{$comment_add.EMAIL}"></p>
			    {/if}
			    {if $comment_add.SHOW_WEBSITE}
				<p><label for="website_url">{'Website'|translate} :</label></p>
				<p><input type="text" name="website_url" id="website_url" value="{$comment_add.WEBSITE_URL}"></p>
			    {/if}
			    <p><label for="contentid">{'Comment'|translate} ({'mandatory'|translate}) :</label></p>
			    <p><textarea name="content" id="contentid" rows="5" cols="50">{$comment_add.CONTENT}</textarea></p>
			    <p><input type="hidden" name="key" value="{$comment_add.KEY}">
				<input type="submit" value="{'Submit'|translate}"></p>
			</form>
		    </div>
		{/if}
		{if isset($comments)}
		    <div id="pictureCommentList">
			{if (($COMMENT_COUNT > 2) || !empty($navbar))}
			    <div id="pictureCommentNavBar">
				{if $COMMENT_COUNT > 2}
				    <a href="{$COMMENTS_ORDER_URL}#comments" rel="nofollow" class="commentsOrder">{$COMMENTS_ORDER_TITLE}</a>
				{/if}
				{if !empty($navbar) }{include file="navigation_bar.tpl"}{/if}
			    </div>
			{/if}
			{include file='comment_list.tpl'}
		    </div>
		{/if}
	    </div>
	</div>
    {/if}
    {if !empty($PLUGIN_PICTURE_AFTER)}{$PLUGIN_PICTURE_AFTER}{/if}
{/block}

{block name="context"}
    {if $DISPLAY_NAV_THUMB}
	<div class="image-number">{$PHOTO}</div>
	<div class="infos navigation">
	    {if isset($previous)}
		<a class="navigation-thumbnail" href="{$previous.U_IMG}" title="{'Previous'|translate} : {$previous.TITLE_ESC}" rel="prev">
		    <img src="{$previous.derivatives.square->get_url()}" alt="{$previous.TITLE_ESC}">
		</a>
	    {elseif isset($U_UP)}
		<a class="navigation-thumbnail" href="{$U_UP}" title="{'Thumbnails'|translate}" style="{$U_UP_SIZE_CSS}">
		    {'First Page'|translate}
		    {'Go back to the album'|translate}
		</a>
	    {/if}
	    {if isset($next)}
		<a class="navigation-thumbnail" href="{$next.U_IMG}" title="{'Next'|translate} : {$next.TITLE_ESC}" rel="next">
		    <img src="{$next.derivatives.square->get_url()}" alt="{$next.TITLE_ESC}">
		</a>
	    {elseif isset($U_UP)}
		<a class="navigation-thumbnail" href="{$U_UP}" title="{'Thumbnails'|translate}" style="{$U_UP_SIZE_CSS}">
		    {'Last Page'|translate}
		    {'Go back to the album'|translate}
		</a>
	    {/if}
	</div>
    {/if}

    {if isset($current.unique_derivatives) && count($current.unique_derivatives)>1}
	<div class="infos photo-sizes">
	    <h3>{'Photo sizes'|translate}</h3>
	    <ul>
		{foreach $current.unique_derivatives as $derivative_type => $derivative}
		    <li>
			<i class="{if $derivative->get_type()!=$current.selected_derivative->get_type()}visually-hidden{/if}">&#x2714;</i>
			<a href="{$U_CANONICAL}&display={$derivative_type}">{$derivative_type|translate}&nbsp;({$derivative->get_size_hr()})</a>
		    </li>
		{/foreach}
	    </ul>
	    {if isset($U_ORIGINAL)}
		<a href="{$U_ORIGINAL}">{'Original'|translate}</a>
	    {/if}
	</div>
    {/if}

    <div class="infos">
	<h3>{'Informations'|translate}</h3>
	{if $display_info.author and isset($INFO_AUTHOR)}
	    <p class="info-author"><span class="key">{'Author'|translate}</span>:&nbsp;{$INFO_AUTHOR}</p>
	{/if}
	{if $display_info.created_on and isset($INFO_CREATION_DATE)}
	    <p class="info-creation-date"><span class="key">{'Created on'|translate}</span>:&nbsp;{$INFO_CREATION_DATE}</p>
	{/if}
	{if $display_info.posted_on}
	    <p class="info-posted-date"><span class="key">{'Posted on'|translate}</span>:&nbsp;{$INFO_POSTED_DATE}</p>
	{/if}
	{if $display_info.dimensions and isset($INFO_DIMENSIONS)}
	    <p class="info-dimensions"><span class="key">{'Dimensions'|translate}</span>:&nbsp;{$INFO_DIMENSIONS}</p>
	{/if}
	{if $display_info.file}
	    <p class="info-file"><span class="key">{'File'|translate}</span>:&nbsp;{$INFO_FILE}</p>
	{/if}
	{if $display_info.filesize and isset($INFO_FILESIZE)}
	    <p><span class="key">{'Filesize'|translate}</span>:&nbsp;{$INFO_FILESIZE}</p>
	{/if}
	{if $display_info.tags}
	    <div class="info-tags">
		<span{if $TAGS_PERMISSION_ADD} class="edit-tags"{/if}>{'Tags'|translate}</span>
		<div>
		    {if !empty($related_tags)}
			{foreach $related_tags as $tag}
			    <a {if !$tag.validated}class="pending{if $tag.status==1} added{else} deleted{/if}"{/if} href="{$tag.URL}">{$tag.name}</a>{if !$tag@last},{/if}
			{/foreach}
		    {/if}

		    <div class="visually-hidden">
			{if $TAGS_PERMISSION_ADD}
			    <form action="{$USER_TAGS_UPDATE_SCRIPT}" method="post" id="user-tags-form" class="js-hidden">
				<select name="user_tags[]" id="user-tags" multiple="multiple">
				    {foreach $related_tags as $tag}
					<option value="~~{$tag.id}~~" selected="selected">{$tag.name}</option>
				    {/foreach}
				</select>
				<input type="hidden" name="image_id" value="{$current.id}">
				<input id="user-tags-update" name="user_tags_update" type="submit" value="{'Update tags'|translate}">
			    </form>
			{/if}
		    </div>
		</div>
	    </div>
	{/if}
	{if $display_info.categories and isset($related_categories)}
	    <p class="info-categories">
		<span class="key">{'Albums'|translate}</span>:&nbsp;
		<ul>
		    {foreach $related_categories as $cat}
			<li>{$cat}</li>
		    {/foreach}
		</ul>
	    </p>
	{/if}

	{if $display_info.visits}
	    <p class="info-visits"><span class="key">{'Visits'|translate}</span>:&nbsp;{$INFO_VISITS}</p>
	{/if}
    </div>

    {if isset($rating)}
	<div class="infos rating">
	    <h3>{'Rate'|translate}</h3>
	    {if $display_info.rating_score and isset($rate_summary)}
		<p class="info-rating_score"><span class="key">{'Rating score'|translate}</span>:&nbsp;
		    {if $rate_summary.count}
			<span id="rating-score">{$rate_summary.score}</span> <span id="rating-count">({$rate_summary.count|translate_dec:'%d rate':'%d rates'})</span>
		    {else}
			<span id="rating-score">{'no rate'|translate}</span> <span id="rating-count"></span>
		    {/if}
		</p>
	    {/if}

	    <div class="rating">
		<span class="key">{if isset($rating.USER_RATE)}{'Update your rating'|translate}{else}{'Rate this photo'|translate}{/if}</span>
		<p>
		    <form action="{$rating.F_ACTION}" method="post" id="rate-form" name="rate_form">
			{foreach $rating.marks as $mark}
			    <label for="rate-{$mark}">{$mark}</label>
			    <input type="radio" name="rate" id="rate-{$mark}" value="{$mark}"{if isset($rating.USER_RATE) && $mark==$rating.USER_RATE} class="selected"{/if}>
			{/foreach}
		    </form>
		</p>
	    </div>
	</div>
    {/if}


    {if $display_info.privacy_level and isset($available_permission_levels)}
	<div class="infos privacy_level">
	    <h3>{'Who can see this photo?'|translate}</h3>
	    <ul class="visually-hiddena">
		{foreach $available_permission_levels as $level => $label}
		    <li>
			<i class="{if $level != $current.level} visually-hidden{/if}">&#x2714;</i>
			<a href="{$U_CANONICAL}&level={$level}">{$label}</a>
		    </li>
		{/foreach}
	    </ul>
	</div>
    {/if}

    {if isset($metadata)}
	<div class="infos metadata">
	    {foreach $metadata as $meta}
		<h3>{$meta.TITLE}</h3>
		{foreach $meta.lines as $value => $label}
		    <p><span class="key">{$label}</span>:&nbsp;{$value}</p>
		{/foreach}
	    {/foreach}
	</div>
    {/if}
{/block}
