{extends file="__layout.tpl"}

{combine_css path="themes/legacy/css/no_photo_yet.css"}

{block name="category-actions"}{/block}

{block name="content"}
    <div id="global">
	{if $step == 1}
	    <p id="noPhotoWelcome">{'Welcome to your Piwigo photo gallery!'|translate}</p>

	    <form method="post" action="{$U_LOGIN}" id="quickconnect">
		{'Username'|translate}
		<br><input type="text" name="username">
		<br>
		<br>{'Password'|translate}
		<br><input type="password" name="password">

		<p><input class="submit" type="submit" name="login" value="{'Login'|translate}"></p>

	    </form>
	    <div id="deactivate"><a href="{$deactivate_url}">{'... or browse your empty gallery'|translate}</a></div>


	{else}
	    <p id="noPhotoWelcome">{$intro}</p>
	    <div class="bigButton"><a href="{$next_step_url}">{'I want to add photos'|translate}</a></div>
	    <div id="deactivate"><a href="{$deactivate_url}">{'... or please deactivate this message, I will find my way by myself'|translate}</a></div>
	{/if}
    </div>
{/block}
