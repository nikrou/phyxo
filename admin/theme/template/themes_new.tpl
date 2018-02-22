{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Themes'|translate}</a></li>
    <li class="breadcrumb-item">{'Add New Theme'|translate}</li>
{/block}

{block name="content"}
    {include file='include/colorbox.inc.tpl'}
    {footer_script}{literal}
    $(function() {
    $("a.preview-box").colorbox();
    });
    {/literal}{/footer_script}
    {if not empty($new_themes)}
	<div class="themeBoxes">
	    {foreach $new_themes as $theme}
		<div class="themeBox">
		    <div class="themeName">{$theme.name}</div>
		    <div class="themeShot"><a href="{$theme.screenshot}" class="preview-box icon-zoom-in" title="{$theme.name}"><img src="{$theme.thumbnail}" onerror="this.src='{$default_screenshot}'"></a></div>
		    <div class="themeActions"><a href="{$theme.install_url}">{'Install'|translate}</a></div>
		</div>
	    {/foreach}
	</div> <!-- themeBoxes -->
    {else}
	<p>{'There is no other theme available.'|translate}</p>
    {/if}
{/block}
