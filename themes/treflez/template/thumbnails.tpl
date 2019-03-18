{extends file="__layout.tpl"}

{block name="content"}
    {include file="category_nav.tpl"}

    <div id="content" class="{if $category_view == 'list'}content-list{else}content-grid{/if}">
	{if !empty($category_thumbnails)} {* display sub-albums *}
	    {include file="_albums.tpl"}
	{/if}

	{if !empty($thumbnails)}
	    {include file="_thumbnails.tpl"}
	{/if}
    </div>
{/block}
