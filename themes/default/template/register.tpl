{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}
{block name="menubar"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'Registration'|translate}</h2>
{/block}

{block name="main-content"}
    <div class="form-content">
	<form method="post" action="{$F_ACTION}" class="properties" name="register_form">
	    <div class="fieldset">
		<h3>{'Enter your personnal informations'|translate}</h3>

		<p>
		    <label class="required" for="login">
			<abbr title="{'Required field'|translate}">*</abbr>
			{'Username'|translate}
		    </label>
		    <input type="text" name="login" id="login" value="{$F_LOGIN}">
		</p>

		<p>
		    <label class="required" for="password">
		    	<abbr title="{'Required field'|translate}">*</abbr>
			{'Password'|translate}
		    </label>
		    <input type="password" name="password" id="password">
		</p>

		<p>
		    <label class="required" for="password_conf">
			<abbr title="{'Required field'|translate}">*</abbr>
			{'Confirm Password'|translate}
		    </label>
		    <input type="password" name="password_conf" id="password_conf">
		</p>
		<p>
		    <label for="mail_address"{if $obligatory_user_mail_address} class="required"{/if}>
			{if $obligatory_user_mail_address}<abbr title="{'Required field'|translate}">*</abbr>{/if}
			{'Email address'|translate}
		    </label>
		    <input type="text" name="mail_address" id="mail_address" value="{$F_EMAIL}">
		    {if not $obligatory_user_mail_address}
			({'useful when password forgotten'|translate})
		    {/if}
		</p>
		<p>
		    <label for="send_password_by_mail">
			<input type="checkbox" name="send_password_by_mail" id="send_password_by_mail" value="1" checked="checked">
			{'Send my connection settings by email'|translate}
		    </label>
		</p>
	    </div>

	    <p class="bottomButtons">
		<input type="hidden" name="key" value="{$F_KEY}" >
		<input class="submit" type="submit" name="submit" value="{'Register'|translate}">
		<input class="submit" type="reset" value="{'Reset'|translate}">
	    </p>
	</form>
    </div>
{/block}
