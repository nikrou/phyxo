<div>
    {if !empty($related_tags)}
	<div id="Tags" class="imageInfo">
	    {foreach $related_tags as $tag}
		<a class="btn btn-primary btn-raised mr-1{if !$tag.validated} pending{if $tag.status==1} added{else} deleted{/if}{/if}" href="{$tag.URL}">{$tag.name}</a>
	    {/foreach}
	</div>
    {/if}

    {if $TAGS_PERMISSION_ADD}
	<form action="{$USER_TAGS_UPDATE_SCRIPT}" method="post" id="user-tags-form" class="js-hidden mt-2">
	    <select name="user_tags[]" id="user-tags" multiple="multiple">
		{if !empty($related_tags)}
		    {foreach $related_tags as $tag}
			<option value="~~{$tag.id}~~" selected="selected">{$tag.name}</option>
		    {/foreach}
		{/if}
	    </select>
	    <input type="hidden" name="image_id" value="{$current.id}">
	    <input id="user-tags-update" class="btn btn-primary mt-2" name="user_tags_update" type="submit" value="{'Update tags'|translate}">
	</form>
    {/if}
</div>
