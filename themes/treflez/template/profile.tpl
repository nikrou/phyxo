{extends file="__layout.tpl"}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{'Profile'|translate}
	    </div>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<form method="post" name="profile" action="{$F_ACTION}" id="profile" class="form-horizontal">
            <div class="card">
		<h4 class="card-header">
                    {'Registration'|translate}
		</h4>
		<div class="card-body">
		    <div class="form-group row">
			<label for="username" class="col-12 col-md-3 col-form-label">{'Username'|translate}</label>
			<div class="col-12 col-md-4">
			    <input id="username" class="form-control-plaintext" type="text" value="{$USERNAME}" readonly/>
			</div>
		    </div>
		    {if not $SPECIAL_USER} {* can modify password + email*}
			<div class="form-group row">
			    <label for="mail_address" class="col-12 col-md-3 col-form-label">{'Email address'|translate}</label>
			    <div class="col-12 col-md-4">
				<input type="text" name="_mail_address" id="mail_address" class="form-control" value="{$EMAIL}" placeholder="{'Email address'|translate}">
			    </div>
			</div>
			<div class="form-group row">
			    <label for="password" class="col-12 col-md-3 col-form-label">{'Password'|translate}</label>
			    <div class="col-12 col-md-4">
				<input type="password" name="_password" id="password" class="form-control" value="" placeholder="{'Password'|translate}">
			    </div>
			</div>
			<div class="form-group row">
			    <label for="new_password" class="col-12 col-md-3 col-form-label">{'New password'|translate}</label>
			    <div class="col-12 col-md-4">
				<input type="password" name="_new_password" id="new_password" class="form-control" value="" placeholder="{'New password'|translate}">
			    </div>
			</div>
			<div class="form-group row">
			    <label for="password_confirm" class="col-12 col-md-3 col-form-label">{'Confirm Password'|translate}</label>
			    <div class="col-12 col-md-4">
				<input type="password" name="_new_password_confirm" id="password_confirm" class="form-control" value="" placeholder="{'Confirm Password'|translate}">
			    </div>
			</div>
		    {/if}
		    {if !$ALLOW_USER_CUSTOMIZATION}
			<div class="form-group row">
			    <div class="col-12 col-md-offset-2 col-12 col-md-10">
				<input class="btn btn-primary btn-raised" type="submit" name="validate" value="{'Submit'|translate}">
				<input class="btn btn-info btn-raised" type="reset" name="reset" value="{'Reset'|translate}">
			    </div>
			</div>
		    {/if}
		</div>
            </div>
	    {if $ALLOW_USER_CUSTOMIZATION}
		<div class="card my-3">
		    <h4 class="card-header">
			{'Preferences'|translate}
		    </h4>
		    <div class="card-body">
			<div class="form-group row">
			    <label for="nb_image_page" class="col-12 col-md-3 col-form-label">{'Number of photos per page'|translate}</label>
			    <div class="col-12 col-md-1">
				<input type="text" maxlength="3" name="nb_image_page" id="nb_image_page" class="form-control" value="{$NB_IMAGE_PAGE}">
			    </div>
			</div>
			<div class="form-group row">
			    <label for="theme" class="col-12 col-md-3 col-form-label">{'Theme'|translate}</label>
			    <div class="col-12 col-md-4">
				<select class="form-control" name="theme">
				    {html_options options=$themes selected=$THEME}
				</select>
			    </div>
			</div>
			<div class="form-group row">
			    <label for="language" class="col-12 col-md-3 col-form-label">{'Language'|translate}</label>
			    <div class="col-12 col-md-4">
				<select class="form-control" name="language">
				    {html_options options=$languages selected=$LANGUAGE}
				</select>
			    </div>
			</div>
			<div class="form-group row">
			    <label for="recent_period" class="col-12 col-md-3 col-form-label">{'Recent period'|translate}</label>
			    <div class="col-12 col-md-1">
				<input type="text" size="3" maxlength="2" name="recent_period" id="recent_period" class="form-control" value="{$RECENT_PERIOD}">
			    </div>
			</div>
			<label class="col-12 col-md-3 form-check-label float-left pl-0">{'Expand all albums'|translate}</label>
			<div class="col-12 col-md-6">
			    {foreach $radio_options as $option => $value}
				<div class="form-check form-check-inline radio">
				    <label for="expand_{$value}" class="form-check-label">
					<input name="expand" id="expand_{$value}" type="radio" class="form-check-input" value="{$option}" {if $option === $EXPAND}checked="checked"{/if}>
					{$value}
				    </label>
				</div>
			    {/foreach}
			</div>
			{if $ACTIVATE_COMMENTS}
			    <label class="col-12 col-md-3 form-check-label float-left pl-0">{'Show number of comments'|translate}</label>
			    <div class="col-12 col-md-6">
				{foreach $radio_options as $option => $value}
				    <div class="form-check form-check-inline radio">
					<label for="show_nb_comments_{$value}" class="form-check-label">
					    <input name="show_nb_comments" id="show_nb_comments_{$value}" type="radio" class="form-check-input" value="{$option}" {if $option === $NB_COMMENTS}checked="checked"{/if}>
					    {$value}
					</label>
				    </div>
				{/foreach}
			    </div>
			{/if}
			<label class="col-12 col-md-3 form-check-label float-left pl-0">{'Show number of hits'|translate}</label>
			<div class="col-12 col-md-9">
			    {foreach $radio_options as $option => $value}
				<div class="form-check form-check-inline radio">
				    <label for="show_nb_hits_{$value}" class="form-check-label">
					<input name="show_nb_hits" id="show_nb_hits_{$value}" type="radio" class="form-check-input" value="{$option}" {if $option === $NB_HITS}checked="checked"{/if}>
					{$value}
				    </label>
				</div>
			    {/foreach}
			</div>
		    </div>
		</div>

		<input type="hidden" name="_csrf_token" value="{$csrf_token}">
		<input class="btn btn-primary btn-raised" type="submit" name="validate" value="{'Submit'|translate}">
		<input class="btn btn-info btn-raised" type="reset" name="reset" value="{'Reset'|translate}">
		<input class="btn btn-warning btn-raised" type="submit" name="reset_to_default" value="{'Reset to default values'|translate}">
	    {/if}
	</form>
    </div>
{/block}
