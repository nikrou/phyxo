{extends file="__layout.tpl"}

{block name="menubar"}{/block}

{block name="footer_scripts"}
    {$smarty.block.parent}
    <script>
     var user_tags = user_tags || {};
     user_tags.allow_delete = {$TAGS_PERMISSION_DELETE};
     user_tags.allow_creation = {$TAGS_PERMISSION_ALLOW_CREATION};
     user_tags.ws_getList = '{$USER_TAGS_WS_GETLIST}';
     user_tags.tags_updated = '{"Tags updated"|translate}';
    </script>
{/block}

{block name="breadcrumb"}
    <h2>{$SECTION_TITLE}{$LEVEL_SEPARATOR}{$current.TITLE}</h2>
{/block}

{block name="main-content"}
    <div id="main-image">
	<div class="wrapper-image">
	    <img src="{$current.selected_derivative->get_url()}" alt="{$ALT_IMG}" title="{if isset($COMMENT_IMG)}{$COMMENT_IMG|strip_tags:false|replace:'"':' '}{else}{$current.TITLE_ESC} - {$ALT_IMG}{/if}">
	</div>

	{if isset($COMMENT_IMG)}
	    <p class="image-comment">{$COMMENT_IMG}</p>
	{/if}
    </div>

    {if isset($COMMENT_COUNT)}
	<div class="form-content{if (!isset($comment_add) && ($COMMENT_COUNT == 0))}no-comment{else}{/if}">
	    <h3>{$COMMENT_COUNT|translate_dec:'%d comment':'%d comments'}</h3>

	    {if isset($comment_add)}
		<h4>{'Add a comment'|translate}</h4>
		<form method="post" action="{$comment_add.F_ACTION}" id="addComment">
		    {if $comment_add.SHOW_AUTHOR}
			<p>
			    <label for="author"{if $comment_add.AUTHOR_MANDATORY} class="required"{/if}>
				{if $comment_add.AUTHOR_MANDATORY}
				    <abbr title="{'Required field'|translate}">*</abbr>
				{/if}
				{'Author'|translate}
			    </label>
			    <input type="text" name="author" id="author" value="{$comment_add.AUTHOR}">
			</p>
		    {/if}
		    {if $comment_add.SHOW_EMAIL}
			<p>
			    <label for="email"{if $comment_add.EMAIL_MANDATORY} class="required"{/if}>
				{if $comment_add.EMAIL_MANDATORY}
				    <abbr title="{'Required field'|translate}">*</abbr>
				    {'Email address'|translate}
				{/if}
			    </label>
			    <input type="text" name="email" id="email" value="{$comment_add.EMAIL}">
			</p>
		    {/if}
		    {if $comment_add.SHOW_WEBSITE}
			<p>
			    <label for="website_url">{'Website'|translate}</label>
			    <input type="text" name="website_url" id="website_url" value="{$comment_add.WEBSITE_URL}">
			</p>
		    {/if}
		    <p>
			<label for="contentid" class="required">
			    <abbr title="{'Required field'|translate}">*</abbr>
			    {'Comment'|translate}
			</label>
			<textarea name="content" id="contentid" rows="5" cols="50">{$comment_add.CONTENT}</textarea>
		    </p>
		    <p>
			<input type="hidden" name="key" value="{$comment_add.KEY}">
			<input type="submit" value="{'Submit'|translate}">
		    </p>
		</form>
	    {/if}

	    {if isset($comments)}
		{if $comments|count > 2}
		    <a href="{$COMMENTS_ORDER_URL}#comments" rel="nofollow" class="commentsOrder">{$COMMENTS_ORDER_TITLE}</a>
		    {include file="_navigation_bar.tpl"}
		{/if}
		{include file="_comment_list.tpl"}
	    {/if}
	</div>
    {/if}
{/block}

{block name="context"}
    {if $DISPLAY_NAV_THUMB}
	<div class="infos navigation">
	    {if isset($previous)}
		<a class="navigation-thumbnail" href="{$previous.U_IMG}" title="{'Previous'|translate} : {$previous.TITLE_ESC}" rel="prev">
		    <img src="{$previous.derivatives.square->get_url()}" alt="{$previous.TITLE_ESC}">
		</a>
	    {elseif isset($U_UP)}
		<a class="navigation-thumbnail level-up" href="{$U_UP}" title="{'Thumbnails'|translate}" rel="up" style="{$U_UP_SIZE_CSS}">
		    <i class="fa fa-angle-up"></i>
		    <span class="visually-hidden">
			{'First Page'|translate}
			{'Go back to the album'|translate}
		    </span>
		</a>
	    {/if}
	    {if isset($next)}
		<a class="navigation-thumbnail" href="{$next.U_IMG}" title="{'Next'|translate} : {$next.TITLE_ESC}" rel="next">
		    <img src="{$next.derivatives.square->get_url()}" alt="{$next.TITLE_ESC}">
		</a>
	    {elseif isset($U_UP)}
		<a class="navigation-thumbnail level-up" href="{$U_UP}" title="{'Thumbnails'|translate}" rel="up" style="{$U_UP_SIZE_CSS}">
		    <i class="fa fa-angle-up"></i>
		    <span class="visually-hidden">
			{'Last Page'|translate}
			{'Go back to the album'|translate}
		    </span>
		</a>
	    {/if}
	    <span class="image-number">{$PHOTO}</span>
	</div>
    {/if}

    {if isset($current.unique_derivatives) && count($current.unique_derivatives)>1}
	<div class="infos photo-sizes">
	    <h3>{'Photo sizes'|translate}</h3>
	    <ul>
		{foreach $current.unique_derivatives as $derivative_type => $derivative}
		    <li>
			<i class="{if $derivative->get_type()!=$current.selected_derivative->get_type()}visually-hidden{/if}">&#x2714;</i>
			<a href="{$current.U_IMG}&display={$derivative_type}">{$derivative_type|translate}&nbsp;({$derivative->get_size_hr()})</a>
		    </li>
		{/foreach}
	    </ul>
	    {if isset($U_ORIGINAL)}
		{* open in modal ? *}
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

	    <p class="key">{if isset($rating.USER_RATE)}{'Update your rating'|translate}{else}{'Rate this photo'|translate}{/if}</p>
	    <p>
		<form action="{$rating.F_ACTION}" method="post" id="rate-form" name="rate_form" class="rating-form">
		    {foreach $rating.marks as $mark}
			<input type="radio" name="rate" id="rate-{$mark}" value="{$mark}"{if !empty($rating.USER_RATE) && $mark==$rating.USER_RATE} checked="checked"{/if}>
			<label for="rate-{$mark}"><span>{$mark}</span></label>
		    {/foreach}
		    <p><input type="submit" name="update_rating" value="{'Submit'|translate}"></p>
		</form>
	    </p>
	</div>
    {/if}


    {if $display_info.privacy_level and isset($available_permission_levels)}
	<div class="infos privacy_level">
	    <h3>{'Who can see this photo?'|translate}</h3>
	    <ul>
		{foreach $available_permission_levels as $level => $label}
		    <li>
			<i class="{if $level != $current.level} visually-hidden{/if}">&#x2714;</i>
			<a href="{$current.U_IMG}&level={$level}">{$label}</a>
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

{block name="toolbar"}
    <ul>
	{if isset($current.U_DOWNLOAD)}
	    <li>
		<a href="{$current.U_DOWNLOAD}" title="{'Download this file'|translate}">
		    <i class="fa fa-save"></i><span class="visually-hidden">{'Download'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($PLUGIN_PICTURE_BUTTONS)}{foreach $PLUGIN_PICTURE_BUTTONS as $button}{$button}{/foreach}{/if}
	{if isset($PLUGIN_PICTURE_ACTIONS)}{$PLUGIN_PICTURE_ACTIONS}{/if}
	{if isset($favorite)}
	    <li>
		<a href="{$favorite.U_FAVORITE}" title="{if $favorite.IS_FAVORITE}{'delete this photo from your favorites'|translate}{else}{'add this photo to your favorites'|translate}{/if}">
		    <i class="fa fa-heart{if !$favorite.IS_FAVORITE}-o{/if}"></i><span class="visually-hidden">{'Favorites'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_SET_AS_REPRESENTATIVE)}
	    <li>
		<a href="{$U_SET_AS_REPRESENTATIVE}" title="{'set as album representative'|translate}">
		    <i class="fa fa-star"></i><span class="visually-hidden">{'representative'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_PHOTO_ADMIN)}
	    <li>
		<a href="{$U_PHOTO_ADMIN}" title="{'Edit photo'|translate}">
		    <i class="fa fa-edit"></i><span class="visually-hidden">{'Edit'|translate}</span>
		</a>
	    </li>
	{/if}
	{if isset($U_CADDIE)}
	    <li>
		<a href="{$U_CADDIE}" title="{'Add to caddie'|translate}">
		    <i class="fa fa-cart-plus"></i><span class="visually-hidden">{'Caddie'|translate}</span>
		</a>
	    </li>
	{/if}
    </ul>
{/block}
