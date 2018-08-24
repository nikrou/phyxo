{extends file="__layout.tpl"}

{block name="head_scripts"}
    {$smarty.block.parent}
    {combine_script id='jquery-install' path='admin/theme/js/install.js'}
{/block}

{block name="header"}{/block}
{block name="breadcrumb"}{/block}
{block name="aside"}{/block}

{block name="content"}
    <h2>Phyxo {'Version'|translate} {$RELEASE} - {'Installation'|translate}</h2>

    {if isset($config_creation_failed)}
	<div class="errors">
	    <p>
		<strong>{'Creation of config file local/config/database.inc.php failed.'|translate}</strong>
	    </p>
	    <ul>
		<li>
		    <p>{'You can download the config file and upload it to local/config directory of your installation.'|translate}</p>
		    <p style="text-align:center">
			<input type="button" value="{'Download the config file'|translate}" onClick="window.open('{$config_url}');">
		    </p>
		</li>
		<li>
		    <p>{'An alternate solution is to copy the text in the box above and paste it into the file "local/config/database.inc.php" (Warning : database.inc.php must only contain what is in the textarea, no line return or space character)'|translate}</p>
		    <textarea rows="15" cols="70">{$config_file_content}</textarea>
		</li>
	    </ul>
	</div>
    {/if}

    {if isset($install)}
	<form method="POST" action="{$F_ACTION}" name="install_form" id="install-form">
	    <div class="fieldset">
		<h3>{'Basic configuration'|translate}</h3>

		<p>
		    <label for="language">{'Default gallery language'|translate}</label>
		    <select class="custom-select" id="language" name="language" onchange="document.location = 'install.php?language='+this.options[this.selectedIndex].value;">
			{html_options options=$language_options selected=$language_selection}
		    </select>
		</p>
	    </div>

	    <div class="fieldset">
		<h3>{'Database configuration'|translate}</h3>
		<p>
		    {if count($F_DB_ENGINES)>1}
			<label for="dblayer">{'Database type'|translate}</label>
			<select name="dblayer" id="dblayer" class="custom-select">
			    {html_options options=$F_DB_ENGINES selected=$F_DB_LAYER}
			</select>
		    {else}
			<input type="hidden" name="dbengine" value="{$F_DB_LAYER}">
		    {/if}
		</p>
		<p class="no-sqlite">
		    <label for="dbhost">{'Host'|translate}</label>
		    <input class="form-control" type="text" id="dbhost" name="dbhost" value="{$F_DB_HOST}">
		    <small class="form-text text-muted">{'localhost or other, supplied by your host provider'|translate}</small>
		</p>
		<p class="no-sqlite">
		    <label for="dbuser">{'User'|translate}</label>
		    <input class="form-control" type="text" id="dbuser" name="dbuser" value="{$F_DB_USER}">
		    <small class="form-text text-muted">{'user login given by your host provider'|translate}</small>
		</p>
		<p class="no-sqlite">
		    <label for="dbpasswd">{'Password'|translate}</label>
		    <input class="form-control" type="password" name="dbpasswd" id="dbpasswd" value="">
		    <small class="form-text text-muted">{'user password given by your host provider'|translate}</small>
		</p>
		<p>
		    <label for="dbname">{'Database name'|translate}</label>
		    <input class="form-control" type="text" id="dbname" name="dbname" value="{$F_DB_NAME}">
		    <small class="form-text text-muted">{'also given by your host provider'|translate}</small>
		</p>
		<p>
		    <label for="prefix">{'Database table prefix'|translate}</label>
		    <input class="form-control" type="text" id="prefix" name="prefix" value="{$F_DB_PREFIX}">
		    <small class="form-text text-muted">{'database tables names will be prefixed with it (enables you to manage better your tables)'|translate}</small>
		</p>
	    </div>
	    <div class="fieldset">
		<h3>{'Admin configuration'|translate}</h3>
		<p>
		    <label for="admin_name">{'Username'|translate}</label>
		    <input class="form-control" type="text" id="admin_name" name="admin_name" value="{$F_ADMIN}">
		    <small class="form-text text-muted">{'It will be shown to the visitors. It is necessary for website administration'|translate}</small>
		</p>
		<p>
		    <label for="admin_pass1">{'Password'|translate}</label>
		    <input class="form-control" type="password" id="admin_pass1" name="admin_pass1" value="">
		    <small class="form-text text-muted">{'Keep it confidential, it enables you to access administration panel'|translate}</small>
		</p>
		<p>
		    <label for="admin_pass2">{'Password [confirm]'|translate}</label>
		    <input class="form-control" type="password" id="admin_pass2" name="admin_pass2" value="">
		    <small class="form-text text-muted">{'verification'|translate}</small>
		</p>
		<p>
		    <label for="admin_mail">{'Email address'|translate}</label>
		    <input class="form-control" type="text" name="admin_mail" id="admin_mail" value="{$F_ADMIN_EMAIL}">
		    <small class="form-text text-muted">{'Visitors will be able to contact site administrator with this mail'|translate}</small>
		</p>
	    </div>
	    <div class="fieldset">
		<h3>{'Options'|translate}</h3>
		<p>
		    <label>
			<input type="checkbox" name="send_password_by_mail">
			{'Send my connection settings by email'|translate}
		    </label>
		</p>
	    </div>
	    <p>
		<input class="btn btn-submit" type="submit" name="install" value="{'Start Install'|translate}">
	    </p>
	</form>
    {else}
	<p>
	    <a class="btn btn-success" href="../">{'Visit Gallery'|translate}</a>
	</p>
    {/if}
{/block}
