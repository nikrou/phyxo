{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Configuration'|translate}</a></li>
    <li class="breadcrumb-item">{'General'|translate}</li>
{/block}

{block name="content"}
    <form method="post" action="{$F_ACTION}" class="properties">
	<div class="fieldset">
	    <h3>{'Basic settings'|translate}</h3>
	    <p>
		<label for="gallery_title">{'Gallery title'|translate}</label>
		<input type="text" class="form-control" maxlength="255" size="50" name="gallery_title" id="gallery_title" value="{$main.CONF_GALLERY_TITLE}">
	    </p>

	    <p>
		<label for="page_banner">{'Page banner'|translate}</label>
		<textarea class="form-control" rows="5" cols="50" class="description" name="page_banner" id="page_banner">{$main.CONF_PAGE_BANNER}</textarea>
	    </p>

	    <div id="order_filters">
		<h4>{'Default photos order'|translate}</h4>
		{foreach $main.order_by as $order}
		    <div class="input-group filter {if isset($ORDER_BY_IS_CUSTOM)}transparent{/if}">
			<select class="custom-select" name="order_by[]" {if isset($ORDER_BY_IS_CUSTOM)}disabled{/if}>
			    {html_options options=$main.order_by_options selected=$order}
			</select>
			<div class="input-group-append">
			    {if $order@index==0}
				<button class="input-group-btn add-filter btn btn-success fa fa-plus"><span class="visually-hidden">{'Add a criteria'|translate}</span></button>
			    {else}
				<button class="input-group-btn remove-filter btn btn-danger fa fa-minus"><span class="visually-hidden">{'delete'|translate}</span></button>
			    {/if}
        		</div>
		    </div>
		{/foreach}

		{if isset($ORDER_BY_IS_CUSTOM)}
		    <p class="order_by_is_custom">{'You can\'t define a default photo order because you have a custom setting in your local configuration.'|translate}</p>
		{/if}
	    </div>
	</div>

	<div class="fieldset">
	    <h3>{'Permissions'|translate}</h3>
	    <p>
		<label class="font-checkbox">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="rate" {if ($main.rate)}checked="checked"{/if}>
		    {'Allow rating'|translate}
		</label>

		<label id="rate_anonymous" class="font-checkbox no-bold">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="rate_anonymous" {if ($main.rate_anonymous)}checked="checked"{/if}>
		    {'Rating by guests'|translate}
		</label>
	    </p>
	    <p>
		<label class="font-checkbox">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="allow_user_registration" {if ($main.allow_user_registration)}checked="checked"{/if}>
		    {'Allow user registration'|translate}
		</label>

		<label id="email_admin_on_new_user" class="font-checkbox no-bold">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="email_admin_on_new_user" {if ($main.email_admin_on_new_user)}checked="checked"{/if}>
		    {'Email admins when a new user registers'|translate}
		</label>
	    </p>

	    <p>
		<label class="font-checkbox">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="allow_user_customization" {if ($main.allow_user_customization)}checked="checked"{/if}>
		    {'Allow user customization'|translate}
		</label>

		<label class="font-checkbox">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="obligatory_user_mail_address" {if ($main.obligatory_user_mail_address)}checked="checked"{/if}>
		    {'Mail address is mandatory for registration'|translate}
		</label>
	    </p>
	</div>

	<div class="fieldset">
	    <h3>{'Miscellaneous'|translate}</h3>
	    <p>
		<label>{'Week starts on'|translate}
		    {html_options class="custom-select" name="week_starts_on" options=$main.week_starts_on_options selected=$main.week_starts_on_options_selected}
		</label>
	    </p>

	    <p>
		<strong>{'Save visits in history for'|translate}</strong>

		<label class="font-checkbox no-bold">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="history_guest" {if ($main.history_guest)}checked="checked"{/if}>
		    {'simple visitors'|translate}
		</label>

		<label class="font-checkbox no-bold">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="log" {if ($main.log)}checked="checked"{/if}>
		    {'registered users'|translate}
		</label>

		<label class="font-checkbox no-bold">
		    <i class="fa fa-check-square"></i>
		    <input type="checkbox" name="history_admin" {if ($main.history_admin)}checked="checked"{/if}>
		    {'administrators'|translate}
		</label>
	    </p>

	    <h4>{'Mail theme'|translate}</h4>
	    <div class="themeBoxes font-checkbox">
		{foreach $main.mail_theme_options as $theme => $theme_name}
		    <div class="themeBox {if $main.mail_theme==$theme}themeDefault{/if}">
			<label>
			    <div class="themeName">
				<i class="fa fa-check-square"></i>
				<input type="radio" name="mail_theme" value="{$theme}" {if $main.mail_theme==$theme}checked{/if}>
				{$theme_name}
			    </div>
			</label>
		    </div>
		{/foreach}
	    </div>
	</div>

	<p>
	    <input type="hidden" name="pwg_token" value="{$csrf_token}">
	    <input type="submit" class="btn btn-submit" name="submit" value="{'Save Settings'|translate}">
	</p>

    </form>
{/block}
