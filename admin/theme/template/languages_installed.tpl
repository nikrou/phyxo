{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Languages'|translate}</a></li>
    <li class="breadcrumb-item">{'Installed Languages'|translate}</li>
{/block}

{block name="content"}
    {foreach from=$language_states item=language_state}
	<div class="state state-{$language_state}">
	    {if $language_state === 'active'}
		<h3>{'Active Languages'|translate}</h3>
	    {elseif $language_state === 'inactive'}
		<h3>{'Inactive Languages'|translate}</h3>
	    {/if}

	    <div class="languages">
		{foreach $languages as $language}
		    {if $language.state === $language_state}
			<div class="language{if $language.is_default} language-default{/if}">
			    <div class="language-name">{$language.name}{if $language.is_default} <em>({'default'|translate})</em>{/if}</div>
			    <div class="language-actions">
				<div>
				    {if $language_state === 'active'}
					{if $language.deactivable}
					    <a href="{$language.u_action}&amp;action=deactivate" class="deactivate" title="{'Forbid this language to users'|translate}">{'Deactivate'|translate}</a>
					{else}
					    <span title="{$language.deactivate_tooltip}">{'Deactivate'|translate}</span>
					{/if}

					{if not $language.is_default}
					    | <a href="{$language.u_action}&amp;action=set_default" title="{'Set as default language for unregistered and new users'|translate}">{'Default'|translate}</a>
					{/if}
				    {/if}

				    {if $language_state === 'inactive'}
					<a href="{$language.u_action}&amp;action=activate" class="activate" title="{'Make this language available to users'|translate}">{'Activate'|translate}</a>
					| <a href="{$language.u_action}&amp;action=delete" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');" class="tiptip" title="{'Delete this language'|translate}">{'Delete'|translate}</a>
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
