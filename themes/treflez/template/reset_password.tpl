{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{$title}
	    </div>
            <ul class="nav navbar-nav">
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
            </ul>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<form action="{$reset_password_action}" method="post" class="form-horizontal">
	    <div class="card col-lg-6">
		<h4 class="card-header">
		    {'Forgot your password?'|translate}
		</h4>
		<div class="card-body">
		    <div class="form-group">
			{'Enter your new password below.'|translate}
		    </div>
		    <div class="form-group">
			<label for="password" class="control-label">{'New password'|translate}</label>
			<input type="password" name="_password" id="password" value="" class="form-control" placeholder="{'New password'|translate}">
		    </div>
		    <div class="form-group">
			<label for="password_confirmation" class="control-label">{'Confirm Password'|translate}</label>
			<input type="password" name="_password_confirmation" id="password_confirmation" class="form-control" value="" placeholder="{'Confirm Password'|translate}">
		    </div>
		    <div class="form-group">
			<input type="hidden" name="_csrf_token" value="{$csrf_token}">
			<input type="submit" name="submit" value="{'Submit'|translate}" class="btn btn-primary">
		    </div>
		</div>
	    </div>
	</form>
    </div>
{/block}
