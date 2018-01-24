{extends file="__layout.tpl"}

{block name="menubar"}{/block}
{block name="category-actions"}{/block}
{assign var="MENUBAR" value=null}

{block name="content"}
    <div id="slideshow">
	<div id="imageHeaderBar">
	    <div class="browsePath">
		{if isset($U_SLIDESHOW_STOP) }
		    [ <a href="{$U_SLIDESHOW_STOP}">{'stop the slideshow'|translate}</a> ]
		{/if}
		<h2 class="showtitle">{$current.TITLE}</h2>
	    </div>
	</div>

	<div id="imageToolBar">
	    <div class="imageNumber">{$PHOTO}</div>
	    {include file='picture_nav_buttons.tpl'}
	</div>

	<div id="content">
	    <div id="theImage">
		{$ELEMENT_CONTENT}
		{if isset($COMMENT_IMG)}
		    <p class="showlegend">{$COMMENT_IMG}</p>
		{/if}
	    </div>
	</div>
    </div>
{/block}
