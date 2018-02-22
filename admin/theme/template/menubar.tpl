{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item">{'Menu Management'|translate}</li>
{/block}

{block name="content"}
    {combine_script id="menubar" require="jquery.ui.sortable" load="footer" path="admin/theme/js/menubar.js"}

    <form id="menuOrdering" action="{$F_ACTION}" method="post">
	<ul class="menuUl">
	    {foreach from=$blocks item=block name="block_loop"}
		<li class="menuLi {if $block.pos<0}menuLi_hidden{/if}" id="menu_{$block.reg->get_id()}">
		    <p>
			<span>
			    <strong>{'Hide'|translate} <input type="checkbox" name="hide_{$block.reg->get_id()}" {if $block.pos<0}checked="checked"{/if}></strong>
			</span>

			<i class="drag_button fa fa-move visibility-hidden" title="{'Drag to re-order'|translate}"></i>
			<strong>{$block.reg->get_name()|translate}</strong> ({$block.reg->get_id()})
		    </p>

		    {if $block.reg->get_owner() != 'piwigo'}
			<p class="menuAuthor">
			    {'Author'|translate}: <i>{$block.reg->get_owner()}</i>
			</p>
		    {/if}

		    <p class="menuPos">
			<label>
			    {'Position'|translate} :
			    <input type="text" size="4" name="pos_{$block.reg->get_id()}" maxlength="4" value="{math equation="abs(pos)" pos=$block.pos}">
			</label>
		    </p>
		</li>
	    {/foreach}
	</ul>
	<p class="menuSubmit">
	    <input type="submit" name="submit" value="{'Submit'|translate}">
	    <input type="submit" name="reset" value="{'Reset'|translate}">
	</p>

    </form>
{/block}
