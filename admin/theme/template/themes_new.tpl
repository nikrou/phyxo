{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Themes'|translate}</a></li>
    <li class="breadcrumb-item">{'Add New Theme'|translate}</li>
{/block}

{block name="content"}
    {if not empty($themes)}
	<div class="extensions">
	    {foreach $themes as $theme}
		<div class="extension theme">
		    <div>{$theme.name}</div>
		    <a href="{$theme.screenshot}" class="preview-box icon-zoom-in" title="{$theme.name}">
			<img src="{$theme.thumbnail}" alt="">
		    </a>
		    <a class="btn btn-sm btn-submit" href="{$theme.install}">{'Install'|translate}</a>
		</div>
	    {/foreach}
	</div>
    {else}
	<p>{'There is no other theme available.'|translate}</p>
    {/if}
{/block}
