{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}
{block name="menubar"}{/block}

{block name="breadcrumb"}
    <h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{$title}</h2>
{/block}

{block name="log_wrapper"}{/block}

{block name="main-content"}
    <div class="form-content">
	{include file="_infos_errors.tpl"}

	{if $action ne 'none'}
	    <form id="lostPassword" action="{$form_action}?action={$action}{if isset($key)}&amp;key={$key}{/if}" method="post">
		<div class="fieldset">
		    <h3>{'Forgot your password?'|translate}</h3>
		    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

		    {if $action eq 'lost'}
			<div class="message">{'Please enter your username or email address.'|translate} {'You will receive a link to create a new password via email.'|translate}</div>

			<p>
			    <label for="username_or_email">{'Username or email'|translate}</label>
			    <input type="text" id="username_or_email" name="username_or_email" size="40" maxlength="40"{if isset($username_or_email)} value="{$username_or_email}"{/if}>
			</p>

			<p class="bottomButtons"><input type="submit" name="submit" value="{'Change my password'|translate}"></p>
		    {elseif $action eq 'reset'}
			<div class="message">{'Hello'|translate} <em>{$username}</em>. {'Enter your new password below.'|translate}</div>

			<p>
			    <label for="use_new_pwd">{'New password'|translate}</label>
			    <input type="password" name="use_new_pwd" id="use_new_pwd" value="">
			</p>

			<p>
			    <label for="confirm_pwd">{'Confirm Password'|translate}</label>
			    <input type="password" name="passwordConf" id="confirm_pwd" value="">
			</p>
		    {/if}
		</div>
		<p><input type="submit" name="submit" value="{'Submit'|translate}"></p>
	    </form>
	{/if}
    </div>
{/block}
