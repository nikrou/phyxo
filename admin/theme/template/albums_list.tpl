{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album list management'|translate}</li>
{/block}

{block name="content"}
    {combine_script id="cat_list" require="jquery.ui.sortable" load="footer" path="admin/theme/js/cat_list.js"}

    <p>
	<a class="btn btn-submit" data-toggle="collapse" href="#create-album">{'create a new album'|translate}</a>
	{if !empty($categories)}<a class="btn btn-submit2" data-toggle="collapse" href="#apply-automatic-sort-order">{'apply automatic sort order'|translate}</a>{/if}
	{if !empty($PARENT_EDIT)}<a class="btn btn-edit" href="{$PARENT_EDIT}"></span>{'edit'|translate}</a>{/if}
    </p>

    <div id="create-album"  class="collapse">
	<form id="formCreateAlbum" action="{$F_ACTION}" method="post">
	    <div class="fieldset">
		<h3>{'create a new album'|translate}</h3>
		<input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

		<p>
		    <label for="virtual_name">{'Album name'|translate}</label>
		    <input type="text" name="virtual_name" id="virtual_name" maxlength="255">
		</p>

		<p>
		    <input class="btn btn-submit" name="submitAdd" type="submit" value="{'Create'|translate}">
		    <a class="btn btn-cancel" href="#create-album" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
	    </div>
	</form>
    </div>

    {if count($categories)}
	<form id="apply-automatic-sort-order" action="{$F_ACTION}" method="post" class="collapse">
	    <div class="fieldset">
		<h3>{'Automatic sort order'|translate}</h3>
		<input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

		<p><strong>{'Sort order'|translate}</strong>
		    {foreach from=$sort_orders key=sort_code item=sort_label}
			<br><label><input type="radio" value="{$sort_code}" name="order_by" {if $sort_code eq $sort_order_checked}checked="checked"{/if}> {$sort_label}</label>
		    {/foreach}
		</p>

		<p>
		    <label><input type="checkbox" name="recursive"> <strong>{'Apply to sub-albums'|translate}</strong></label>
		</p>

		<p>
		    <input class="btn btn-submit" name="submitAutoOrder" type="submit" value="{'Save order'|translate}">
		    <a href="#apply-automatic-sort-order" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
	    </div>
	</form>
    {/if}

    <form id="categoryOrdering" action="{$F_ACTION}" method="post">
	<p id="manualOrder" class="collapse">
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	    <input class="btn btn-submit3" name="submitManualOrder" type="submit" value="{'Save manual order'|translate}">
	    {'... or '|translate} <a href="#manualOrder" class="btn btn-cancel" data-toggle="collapse">{'cancel manual order'|translate}</a>
	</p>

	{if count($categories)}
	    <div class="albums">
		{foreach $categories as $category}
		    <div class="album{if $category.IS_VIRTUAL} virtual_cat{/if}" id="cat_{$category.ID}">
			<p class="album-title">
			    <i class="drag_button move visibility-hidden" title="{'Drag to re-order'|translate}"></i>
			    <strong><a href="{$category.U_CHILDREN}" title="{'manage sub-albums'|translate}">{$category.NAME}</a></strong>
			    <span class="albumInfos"><span class="userSeparator">&middot;</span> {$category.NB_PHOTOS|translate_dec:'%d photo':'%d photos'} <span class="userSeparator">&middot;</span> {$category.NB_SUB_PHOTOS|translate_dec:'%d photo':'%d photos'} {$category.NB_SUB_ALBUMS|translate_dec:'in %d sub-album':'in %d sub-albums'}</span>
			</p>

			<input type="hidden" name="catOrd[{$category.ID}]" value="{$category.RANK}">

			<p class="album-actions">
			    <a href="{$category.U_EDIT}"><i class="fa fa-pencil"></i>{'Edit'|translate}</a>
			    | <a href="{$category.U_CHILDREN}"><i class="fa fa-sitemap"></i>{'manage sub-albums'|translate}</a>
			    {if isset($category.U_SYNC) }
				| <a href="{$category.U_SYNC}"><i class="fa fa-exchange"></i>{'Synchronize'|translate}</a>
			    {/if}
			    {if isset($category.U_DELETE) }
				| <a href="{$category.U_DELETE}" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');"><i class="fa fa-trash"></i>{'delete album'|translate}</a>
			    {/if}
			    {if cat_admin_access($category.ID)}
				| <a href="{$category.U_JUMPTO}"><i class="fa fa-eye"></i> {'jump to album'|translate}</a>
			    {/if}
			</p>
		    </div>
		{/foreach}
	    </div>
	{/if}
    </form>
{/block}
