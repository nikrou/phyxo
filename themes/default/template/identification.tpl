{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}
{block name="menubar"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'Identification'|translate}</h2>
{/block}

{block name="main-content"}
    <div class="form-content">
	{include file="_infos_errors.tpl"}

	<form action="{$F_LOGIN_ACTION}" method="post" name="login_form" class="properties">
	    <div class="fieldset">
		<h3>{'Connection settings'|translate}</h3>
		<p>
		    <label for="username">{'Username'|translate}</label>
		    <input tabindex="1" class="login" type="text" name="username" id="username" size="25">
		</p>

		<p>
		    <label for="password">{'Password'|translate}</label>
		    <input tabindex="2" class="login" type="password" name="password" id="password" size="25">
		</p>

		{if $authorize_remembering }
		    <p>
			<label for="remember_me"><input tabindex="3" type="checkbox" name="remember_me" id="remember_me" value="1">&nbsp;{'Auto login'|translate}</label>
		    </p>
		{/if}

		<p>
		    <input type="hidden" name="redirect" value="{$U_REDIRECT|@urlencode}">
		    <input tabindex="4" type="submit" name="login" value="{'Submit'|translate}">
		</p>
	    </div>

	    <p>
		{if isset($U_REGISTER)}
		    <a href="{$U_REGISTER}" title="{'Register'|translate}">{'Register'|translate}</a>
		{/if}
		{if isset($U_LOST_PASSWORD)}
		    <a href="{$U_LOST_PASSWORD}" title="{'Forgot your password?'|translate}">{'Forgot your password?'|translate}</a>
		{/if}
	    </p>
	</form>
    </div>
{/block}
