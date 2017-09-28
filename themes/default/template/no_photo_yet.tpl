{extends file="__layout.tpl"}

{block name="head"}
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link rel="stylesheet" type="text/css" href="themes/default/css/no_photo_yet.css">
    <title>Phyxo, {'Welcome'|translate}</title>
{/block}

{block name="main-content"}
    {if $step == 1}
	<h1>{'Welcome to your Phyxo photo gallery!'|translate}</h1>

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
	<div class="no-photo-yet">
	    <h1>{$intro}</h1>
	    <div class="big-button"><a href="{$next_step_url}">{'I want to add photos'|translate}</a></div>
	    <p><a href="{$deactivate_url}">{'... or please deactivate this message, I will find my way by myself'|translate}</a></p>
	</div>
    {/if}
{/block}

{block name="header"}{/block}
