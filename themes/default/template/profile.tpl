{extends file="__layout.tpl"}

{block name="context_wrapper"}{/block}
{block name="menubar"}{/block}

{block name="main-content"}
    <div class="titrePage">
	<ul class="categoryActions">
	</ul>
	<h2><a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}{'Profile'|translate}</h2>
    </div>

    {include file="_infos_errors.tpl"}

    <form method="post" name="profile" action="{$F_ACTION}" id="profile">
	<div class="fieldset">
	    <h3>{'Registration'|translate}</h3>
	    <input type="hidden" name="redirect" value="{$REDIRECT}">
	    <p>
		<span class="label">{'Username'|translate}</span>
		{$USERNAME}
	    </p>
	    {if !$SPECIAL_USER} {* can modify password + email*}
		<p>
		    <label for="mail_address">{'Email address'|translate}</label>
		    <input type="text" name="mail_address" id="mail_address" value="{$EMAIL}">
		</p>
		<p>
		    <label for="password">{'Password'|translate}</label>
		    <input type="password" name="password" id="password" value="">
		</p>
		<p>
		    <label for="use_new_pwd">{'New password'|translate}</label>
		    <input type="password" name="use_new_pwd" id="use_new_pwd" value="">
		</p>
		<p>
		    <label for="password-confirmation">{'Confirm Password'|translate}</label>
		    <input type="password" name="passwordConf" id="password-confirmation" value="">
		</p>
	    {/if}
	</div>

	{if $ALLOW_USER_CUSTOMIZATION}
	    <div class="fieldset">
		<h3>{'Preferences'|translate}</h3>
		<p>
		    <label for="nb_image_page">{'Number of photos per page'|translate}</label>
		    <input type="text" size="4" maxlength="3" name="nb_image_page" id="nb_image_page" value="{$NB_IMAGE_PAGE}">
		</p>
		<p>
		    <label for="template-options">{'Theme'|translate}</label>
		    {html_options name="theme" options=$template_options selected="$template_selection" id="template-options"}
		</p>
		<p>
		    <label for="language-options">{'Language'|translate}</label>
		    {html_options name="language" options=$language_options selected="$language_selection" id="language-options"}
		</p>
		<p>
		    <label for="recent-period">{'Recent period'|translate}</label>
		    <input type="text" size="3" maxlength="2" name="recent_period" id="recent-period" value="{$RECENT_PERIOD}">
		</p>
		<p>
		    <label for="expand">{'Expand all albums'|translate}</label>
		    {html_radios name="expand" options=$radio_options selected="$EXPAND" id="expand"}
		</p>
		{if $ACTIVATE_COMMENTS}
		    <p>
			<label for="show-nb-comments">{'Show number of comments'|translate}</label>
			{html_radios name="show_nb_comments" options=$radio_options selected="$NB_COMMENTS" id="show-nb-comments"}
		    </p>
		{/if}
		<p>
		    <label for="show-nb-hits">{'Show number of hits'|translate}</label>
		    {html_radios name="show_nb_hits" options=$radio_options selected="$NB_HITS" id="show-nb-hits"}
		</p>
	    </div>
	{/if}

	<p class="bottom-buttons">
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	    <input type="submit" name="validate" value="{'Submit'|translate}">
	    <input type="reset" name="reset" value="{'Reset'|translate}">
	    {if $ALLOW_USER_CUSTOMIZATION}
		<input class="submit" type="submit" name="reset_to_default" value="{'Reset to default values'|translate}">
	    {/if}
	</p>
    </form>
{/block}
