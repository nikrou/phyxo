{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-expand-lg navbar-contextual {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto"><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'About'|translate}</div>
            <ul class="navbar-nav justify-content-end">
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
            </ul>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<div class="card">
            <h4 class="card-header">{'About'|translate}</h4>
            <div class="card-body">
		{$ABOUT_MESSAGE}
		{if isset($THEME_ABOUT) }
		    {$THEME_ABOUT}
		{/if}
	    </div>
	</div>
    </div>
{/block}
