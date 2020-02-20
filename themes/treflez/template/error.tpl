{extends file="__layout.tpl"}

{block name="content"}
    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<h1>Oops! An Error Occurred</h1>
	<h2>The server returned a "{$status_code} {$status_text}".</h2>

	<p><a href="{$LOGIN_URL}">{'Identification'|translate}</a></p>
	<p><a href="{$HOME_URL}">{'Home'|translate}</a></p>
    </div>
{/block}
