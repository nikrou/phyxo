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
	<form action="{$forgot_password_action}" method="post" class="form-horizontal">
	    <div class="card col-lg-6">
		{if empty($infos)}
		<h4 class="card-header">
		    {'Forgot your password?'|translate}
		</h4>
		<div class="card-body">
		    <div class="form-text text-muted">
			{'Please enter your username or email address.'|translate}
			{'You will receive a link to create a new password via email.'|translate}
		    </div>
		    <div class="form-group">
			<label for="username_or_email" class="control-label">{'Username or email'|translate}</label>
			<input type="text" tabindex="1" id="username_or_email" name="_username_or_email" class="form-control" maxlength="40"{if isset($username_or_email)} value="{$username_or_email}"{/if} placeholder="{'Username or email'|translate}">
		    </div>
		    <div class="form-group">
			<div class="col-sm-10">
			    <input type="hidden" name="_csrf_token" value="{$csrf_token}">
			    <input type="submit" name="submit" value="{'Change my password'|translate}" class="btn btn-primary">
			</div>
		    </div>
		</div>
		{/if}
	    </div>
	</form>
    </div>
{/block}
