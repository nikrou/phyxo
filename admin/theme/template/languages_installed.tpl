{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Languages'|translate}</a></li>
    <li class="breadcrumb-item">{'Installed Languages'|translate}</li>
{/block}

{block name="content"}
    {foreach $language_states as $language_state}
	<div class="extensions state state-{$language_state}">
	    {if $language_state === 'active'}
		<h3>{'Active Languages'|translate}</h3>
	    {elseif $language_state === 'inactive'}
		<h3>{'Inactive Languages'|translate}</h3>
	    {/if}

	    <div>
		{foreach $languages as $language}
		    {if $language.state === $language_state}
			<div class="row extension{if $language.is_default} extension-default{/if}">
			    <div class="col-2">
				<div>{$language.name}{if $language.is_default} <em>({'Default'|translate})</em>{/if}</div>
				{if $language_state === 'active'}
				    <div>{'Version'|translate} {$language.CURRENT_VERSION}</div>
				{/if}
			    </div>
			    <div class="col-10">
				<div>
				    {if $language_state === 'active'}
					{if $language.deactivable}
					    <a class="btn btn-sm btn-info" href="{$language.action}" title="{'Forbid this language to users'|translate}">{'Deactivate'|translate}</a>
					{else}
					    <span class="btn btn-sm btn-info disabled" title="{$language.deactivate_tooltip}">{'Deactivate'|translate}</span>
					{/if}

					{if !$language.is_default}
					    <a class="btn btn-sm btn-success" href="{$language.set_default}" title="{'Set as default language for unregistered and new users'|translate}">{'Default'|translate}</a>
					{/if}
				    {/if}

				    {if $language_state === 'inactive'}
					<a class="btn btn-sm btn-submit" href="{$language.action}" class="activate" title="{'Make this language available to users'|translate}">{'Activate'|translate}</a>
					<a class="btn btn-sm btn-danger" href="{$language.delete}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');" title="{'Delete this language'|translate}">{'Delete'|translate}</a>
				    {/if}
				</div>
			    </div> <!-- languageActions -->
			</div> <!-- languageBox -->
		    {/if}
		{/foreach}
	    </div> <!-- languageBoxes -->
	</div>
    {/foreach}
{/block}
