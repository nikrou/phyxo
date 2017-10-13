{block name="menubar-identification"}
    <div class="block">
	<h3>{'Identification'|translate}</h3>
	{if isset($USERNAME)}
	    <p>{'Hello'|translate} {$USERNAME} !</p>
	{/if}
	<ul>
	    {if isset($U_REGISTER)}
		<li><a href="{$U_REGISTER}" title="{'Create a new account'|translate}" >{'Register'|translate}</a></li>
	    {/if}
	    {if isset($U_LOGIN)}
		<li><a href="{$U_LOGIN}" >{'Login'|translate}</a></li>
	    {/if}
	    {if isset($U_LOGOUT)}
		<li><a href="{$U_LOGOUT}">{'Logout'|translate}</a></li>
	    {/if}
	    {if isset($U_PROFILE)}
		<li><a href="{$U_PROFILE}" title="{'customize the appareance of the gallery'|translate}">{'Customize'|translate}</a></li>
	    {/if}
	    {if isset($U_ADMIN)}
		<li><a href="{$U_ADMIN}" title="{'available for administrators only'|translate}">{'Administration'|translate}</a></li>
	    {/if}
	</ul>
	{if isset($U_LOGIN)}
	    <form method="post" action="{$U_LOGIN}" id="quickconnect">
		<div class="fieldset">
		    <h3>{'Quick connect'|translate}</h3>
		    <p>
			<label for="username">{'Username'|translate}</label>
			<input type="text" name="username" id="username" value="">
		    </p>

		    <p>
			<label for="password">{'Password'|translate}</label>
			<input type="password" name="password" id="password">
		    </p>

		    {if $AUTHORIZE_REMEMBERING}
			<p>
			    <label for="remember_me">{'Auto login'|translate}
				<input type="checkbox" name="remember_me" id="remember_me" value="1">
			    </label>
			</p>
		    {/if}

		    <p>
			<input type="hidden" name="redirect" value="{$smarty.server.REQUEST_URI|@urlencode}">
			<input type="submit" name="login" value="{'Submit'|translate}">
			{if isset($U_REGISTER)}
			    <a href="{$U_REGISTER}">{'Create a new account'|translate}</a>
			{/if}
			<a href="{$U_LOST_PASSWORD}">{'Forgot your password?'|translate}</a>
		    </p>
		</div>
	    </form>
	{/if}
    </div>
{/block}
