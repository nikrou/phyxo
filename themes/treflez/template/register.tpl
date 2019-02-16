{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{'Registration'|translate}
	    </div>
            <ul class="navbar-nav justify-content-end">
		{if !empty($PLUGIN_INDEX_ACTIONS)}{$PLUGIN_INDEX_ACTIONS}{/if}
            </ul>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<form method="post" action="{$register_route}" class="form-horizontal" name="register_form">
	    <div class="card col-lg-6">
		<h4 class="card-header">
                    {'Connection settings'|translate}
		</h4>
		<div class="card-body">
                    <div class="form-group">
			<label for="username" class="control-label">{'Username'|translate} *</label>
			<input type="text" tabindex="1" name="_username" id="username" value="{$last_username}" class="form-control" placeholder="{'Username'|translate}" required="required"/>
		    </div>
		    <div class="form-group">
			<label for="password" class="control-label">{'Password'|translate} *</label>
			<input type="password" tabindex="2" name="_password" id="password" class="form-control" placeholder="{'Password'|translate}" required="required"/>
		    </div>
		    <div class="form-group">
			<label for="password_conf" class="control-label">{'Confirm Password'|translate} *</label>
			<input type="password" tabindex="3" name="_password_confirm" id="password_conf" class="form-control" placeholder="{'Confirm Password'|translate}" required="required"/>
		    </div>
		    <div class="form-group">
			<label for="mail_address" class="control-label">{'Email address'|translate}{if $obligatory_user_mail_address} *{/if}</label>
			<input type="email" tabindex="4" name="_mail_address" id="mail_address" value="{$F_EMAIL}" class="form-control" placeholder="{'Email address'|translate}"{if $obligatory_user_mail_address} required="required"{/if}>
			{if not $obligatory_user_mail_address}
			    <p class="form-text text-muted">({'useful when password forgotten'|translate})</p>
			{/if}
		    </div>
		    <div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
			    <div class="custom-control custom-checkbox">
				<input type="checkbox" tabindex="5" class="custom-control-input" name="_send_password_by_mail" id="send_password_by_mail" value="1">
				<label class="custom-control-label" for="send_password_by_mail">{'Send my connection settings by email'|translate}</label>
			    </div>
			</div>
		    </div>

                    <div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
			    <input type="hidden" name="_csrf_token" value="{$csrf_token}">
                            <input type="submit" name="submit" value="{'Register'|translate}" class="btn btn-primary btn-raised">
                            <input type="reset" value="{'Reset'|translate}" class="btn btn-secondary btn-raised">
			</div>
                    </div>
		</div>
            </div>
	</form>
    </div>
{/block}
