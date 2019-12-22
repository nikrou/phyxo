{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Configuration'|translate}</a></li>
    <li class="breadcrumb-item">{'Guest Settings'|translate}</li>
{/block}

{block name="content"}
    <form method="post" name="profile" action="{$F_ACTION}" id="profile" class="properties">
	<div id="configContent">
	    {if $GUEST_USERNAME !== 'guest'}
		<h3>{'The settings for the guest are from the {user} user'|translate:['user' => $GUEST_USERNAME]}</h3>
	    {/if}

	    <div class="fieldset">
		<h3>{'Preferences'|translate}</h3>

		<p>
		    <label for="theme">{'Theme'|translate}</label>
		    <select class="form-control" name="theme">
			{html_options options=$themes selected=$THEME}
		    </select>
		</p>

		<p>
		    <label for="language">{'Language'|translate}</label>
		    <select class="form-control" name="language">
			{html_options options=$languages selected=$LANGUAGE}
		    </select>
		</p>

		<p>
		    <label for="nb_image_page">{'Number of photos per page'|translate}</label>
		    <input class="form-control" type="text" size="4" maxlength="3" name="nb_image_page" id="nb_image_page" value="{$GUEST_NB_IMAGE_PAGE}">
		</p>

		<p>
		    <label for="recent_period">{'Recent period'|translate}</label>
		    <input class="form-control" type="text" size="3" maxlength="2" name="recent_period" id="recent_period" value="{$GUEST_RECENT_PERIOD}">
		</p>
		<p>
		    <span>{'Expand all albums'|translate}</span>
		    {html_radios name='expand' options=$radio_options selected=$GUEST_EXPAND}
		</p>
		{if $GUEST_ACTIVATE_COMMENTS}
		    <p>
			<span>{'Show number of comments'|translate}</span>
			{html_radios name='show_nb_comments' options=$radio_options selected=$GUEST_NB_COMMENTS}
		    </p>
		{/if}

		<p>
		    <span>{'Show number of hits'|translate}</span>
		    {html_radios name='show_nb_hits' options=$radio_options selected=$GUEST_NB_HITS}
		</p>
	    </div>

	    <p class="bottomButtons">
		<input type="hidden" name="pwg_token" value="{$csrf_token}">
		<input class="btn btn-submit" type="submit" name="validate" value="{'Submit'|translate}">
		<input class="btn btn-reset" type="reset" name="reset" value="{'Reset'|translate}">
	    </p>
	</div>
    </form>
{/block}
