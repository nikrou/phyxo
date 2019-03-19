<li id="languageSwitch" class="nav-item dropdown">
    <button type="button" class="btn btn-link nav-link dropdown-toggle" data-toggle="dropdown">
        <span class="pwg-icon langflag-{$lang_switch.Active.code}">&nbsp;</span>
	<span class="pwg-button-text">{'Language'|translate}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-right" role="menu">
	{foreach $lang_switch.flags as $flag}
            <a class="dropdown-item{if $lang_switch.Active.code==$flag.code} active{/if}" href="{$flag.url}">
		{if $lang_info.direction=="ltr"}
                    <span class="pwg-icon langflag-{$flag.code}">&nbsp;</span><span class="langflag-text-ltf">{$flag.title}</span>
		{else}
                    <span class="langflag-text-rtf">{$flag.title}</span><span class="pwg-icon langflag-{$flag.code}">&nbsp;</span>
		{/if}
            </a>
	{/foreach}
    </div>
</li>
