{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{'Identification'|translate}
	    </div>
            <ul class="navbar-nav justify-content-end">
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
            </ul>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<form action="{$login_action}" method="post" name="login_form" class="form-horizontal">
	    {if $error}
		<div class="alert alert-danger">{'Invalid credentials'|translate}</div>
	    {/if}

            <div class="card">
		<h4 class="card-header">
                    {'Connection settings'|translate}
		</h4>
		<div class="card-body">
                    <div class="form-group">
			<label for="username" class="col-sm-2 control-label">{'Username'|translate}</label>
			<div class="col-sm-4">
                            <input tabindex="1" class="form-control" type="text" name="_username" id="username" placeholder="{'Username'|translate}" value="{$last_username}">
			</div>
                    </div>
                    <div class="form-group">
			<label for="password" class="col-sm-2 control-label">{'Password'|translate}</label>
			<div class="col-sm-4">
                            <input tabindex="2" class="form-control" type="password" name="_password" id="password" placeholder="{'Password'|translate}">
			</div>
                    </div>
		    {if $authorize_remembering }
			<div class="form-group">
			    <div class="col-sm-offset-2 col-sm-10">
				<div class="checkbox">
				    <label>
					<input tabindex="3" type="checkbox" name="_remember_me" id="remember_me" value="1"> {'Auto login'|translate}
				    </label>
				</div>
			    </div>
			</div>
		    {/if}
                    <div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
			    <input type="hidden" name="_csrf_token" value="{$csrf_token}">
                            <input tabindex="4" type="submit" name="login" value="{'Submit'|translate}" class="btn btn-primary btn-raised">
			</div>
                    </div>
                    <div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
                            {if isset($U_REGISTER)}
				<a href="{$U_REGISTER}" title="{'Register'|translate}" class="pwg-state-default pwg-button">
                                    <span class="fa fa-user"></span> {'Register'|translate}
				</a>
                            {/if}
                            &nbsp;&nbsp;&nbsp;
                            {if isset($U_LOST_PASSWORD)}
				<a href="{$U_LOST_PASSWORD}" title="{'Forgot your password?'|translate}" class="pwg-state-default pwg-button">
                                    <span class="fa fa-lock"></span> {'Forgot your password?'|translate}
				</a>
                            {/if}
			</div>
                    </div>
		</div>
            </div>
	</form>
    </div>
{/block}
