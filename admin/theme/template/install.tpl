<!DOCTYPE html>
<html lang="{$lang_info.code}" dir="{$lang_info.direction}">
    <head>
	<meta http-equiv="Content-Type" content="text/html; charset={$T_CONTENT_ENCODING}">
	<meta http-equiv="Content-script-type" content="text/javascript">
	<meta http-equiv="Content-Style-Type" content="text/css">
	<link rel="shortcut icon" type="image/x-icon" href="./theme/icon/favicon.ico">

	{get_combined_css}

      	<link rel="stylesheet" href="{asset manifest='theme/build/manifest.json' src='app.css'}">
	<link rel="stylesheet" type="text/css" href="{$ROOT_URL}admin/theme/install.css">

      <!-- BEGIN get_combined_scripts -->
      {get_combined_scripts load='header'}
      <!-- END get_combined_scripts -->

      {combine_script id='jquery' path='admin/theme/js/jquery/jquery.js'}
      {combine_script id='jquery-install' path='admin/theme/js/install.js'}
      <script type="text/javascript">
       $(document).ready(function() {
	   $("a.externalLink").click(function() {
	       window.open($(this).attr("href"));
	       return false;
	   });

	   $("#admin_mail").keyup(function() {
	       $(".adminEmail").text($(this).val());
	   });
       });
      </script>

      {combine_script id="jquery.cluetip" load="async" require="jquery" path="admin/theme/js/plugins/jquery.cluetip.js"}

      {footer_script require="jquery.cluetip"}
      jQuery().ready(function(){ldelim}
      jQuery('.cluetip').cluetip({ldelim}
      width: 300,
      splitTitle: '|',
      positionBy: 'bottomTop'
      });
      });
    {/footer_script}

    <title>Phyxo {$RELEASE} - {'Installation'|translate}</title>
  </head>

  <body>
    <div id="the_page">
      <div id="theHeader"></div>
      <div id="content" class="content">

	<h2>Phyxo {'Version'|translate} {$RELEASE} - {'Installation'|translate}</h2>

	{if isset($config_creation_failed)}
	<div class="errors">
	  <p style="margin-left:30px;">
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

	{if isset($errors)}
	<div class="errors">
	  <ul>
	    {foreach from=$errors item=error}
	    <li>{$error}</li>
	    {/foreach}
	  </ul>
	</div>
	{/if}

	{if isset($infos)}
	<div class="infos">
	  <ul>
	    {foreach from=$infos item=info}
	    <li>{$info}</li>
	    {/foreach}
	  </ul>
	</div>
	{/if}

	{if isset($install)}
	<form method="POST" action="{$F_ACTION}" name="install_form" id="install-form">
	  <fieldset>
	    <legend>{'Basic configuration'|translate}</legend>

	    <table class="table2">
	      <tr>
		<td style="width: 30%">{'Default gallery language'|translate}</td>
		<td>
		  <select name="language" onchange="document.location = 'install.php?language='+this.options[this.selectedIndex].value;">
		    {html_options options=$language_options selected=$language_selection}
		  </select>
		</td>
	      </tr>
	    </table>
	  </fieldset>

	  <fieldset>
	    <legend>{'Database configuration'|translate}</legend>

	    <table class="table2">
	      {if count($F_DB_ENGINES)>1}
	      <tr>
		<td style="width: 30%;">{'Database type'|translate}</td>
		<td>
		  <select name="dblayer" id="dblayer">
		    {html_options options=$F_DB_ENGINES selected=$F_DB_LAYER}
		  </select>
		</td>
		<td>{'Database type'|translate}</td>
		{else}
		<td colspan="3">
		  <input type="hidden" name="dbengine" value="{$F_DB_LAYER}">
		</td>
	      </tr>
	      {/if}
	      <tr class="no-sqlite">
		<td style="width: 30%;" class="fieldname">{'Host'|translate}</td>
		<td><input type="text" name="dbhost" value="{$F_DB_HOST}"></td>
		<td class="fielddesc">{'localhost or other, supplied by your host provider'|translate}</td>
	      </tr>
	      <tr class="no-sqlite">
		<td class="fieldname">{'User'|translate}</td>
		<td><input type="text" name="dbuser" value="{$F_DB_USER}"></td>
		<td class="fielddesc">{'user login given by your host provider'|translate}</td>
	      </tr>
	      <tr class="no-sqlite">
		<td class="fieldname">{'Password'|translate}</td>
		<td><input type="password" name="dbpasswd" value=""></td>
		<td class="fielddesc">{'user password given by your host provider'|translate}</td>
	      </tr>
	      <tr>
		<td class="fieldname">{'Database name'|translate}</td>
		<td><input type="text" name="dbname" value="{$F_DB_NAME}"></td>
		<td class="fielddesc">{'also given by your host provider'|translate}</td>
	      </tr>
	      <tr>
		<td class="fieldname">{'Database table prefix'|translate}</td>
		<td><input type="text" name="prefix" value="{$F_DB_PREFIX}"></td>
		<td class="fielddesc">
		  {'database tables names will be prefixed with it (enables you to manage better your tables)'|translate}
		</td>
	      </tr>
	    </table>

	  </fieldset>
	  <fieldset>
	    <legend>{'Admin configuration'|translate}</legend>

	    <table class="table2">
	      <tr>
		<td style="width: 30%;" class="fieldname">{'Username'|translate}</td>
		<td><input type="text" name="admin_name" value="{$F_ADMIN}"></td>
		<td class="fielddesc">{'It will be shown to the visitors. It is necessary for website administration'|translate}</td>
	      </tr>
	      <tr>
		<td class="fieldname">{'Password'|translate}</td>
		<td><input type="password" name="admin_pass1" value=""></td>
		<td class="fielddesc">{'Keep it confidential, it enables you to access administration panel'|translate}</td>
	      </tr>
	      <tr>
		<td class="fieldname">{'Password [confirm]'|translate}</td>
		<td><input type="password" name="admin_pass2" value=""></td>
		<td class="fielddesc">{'verification'|translate}</td>
	      </tr>
	      <tr>
		<td class="fieldname">{'Email address'|translate}</td>
		<td><input type="text" name="admin_mail" id="admin_mail" value="{$F_ADMIN_EMAIL}"></td>
		<td class="fielddesc">{'Visitors will be able to contact site administrator with this mail'|translate}</td>
	      </tr>
	      <tr>
		<td>{'Options'|translate}</td>
		<td colspan="2">
		  <label>
		    <input type="checkbox" name="send_password_by_mail">
		    {'Send my connection settings by email'|translate}
		  </label>
		</td>
	      </tr>
	    </table>
	  </fieldset>

	  <div style="text-align:center; margin:20px 0 10px 0">
	    <input class="submit" type="submit" name="install" value="{'Start Install'|translate}">
	  </div>
	</form>
	{else}
	<p>
	  <a class="bigButton" href="index.php">{'Visit Gallery'|translate}</a>
	</p>
	{/if}
      </div> {* content *}
    </div> {* the_page *}

    <!-- BEGIN get_combined_scripts -->
    {get_combined_scripts load='footer'}
    <!-- END get_combined_scripts -->
  </body>
</html>
