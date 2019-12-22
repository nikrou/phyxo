{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album list management'|translate}</li>
{/block}

{block name="content"}
    <p>
	<a class="btn btn-submit" data-toggle="collapse" href="#create-album">{'Create a new album'|translate}</a>
	{if !empty($categories)}<a class="btn btn-submit" data-toggle="collapse" href="#apply-automatic-sort-order">{'Apply automatic sort order'|translate}</a>{/if}
	{if !empty($PARENT_EDIT)}<a class="btn btn-edit" href="{$PARENT_EDIT}"></span>{'Edit'|translate}</a>{/if}
    </p>

    <div id="create-album" class="collapse">
	<form id="formCreateAlbum" action="{$F_ACTION_CREATE}" method="post">
	    <div class="fieldset">
		<h3>{'Create a new album'|translate}</h3>
		<p>
		    <label for="virtual_name">{'Album name'|translate}</label>
		    <input class="form-control" type="text" name="virtual_name" id="virtual_name" maxlength="255">
		</p>

		<p>
		    <input class="btn btn-submit" name="submitAdd" type="submit" value="{'Create'|translate}">
		    <input type="hidden" name="pwg_token" value="{$csrf_token}">
		    <a class="btn btn-cancel" href="#create-album" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
	    </div>
	</form>
    </div>

    {if count($categories)}
	<form id="apply-automatic-sort-order" action="{$F_ACTION_UPDATE}" method="post" class="collapse">
	    <div class="fieldset">
		<h3>{'Automatic sort order'|translate}</h3>

		<p><strong>{'Sort order'|translate}</strong></p>
		{foreach $sort_orders as $sort_code => $sort_label}
		    <p><label><input type="radio" value="{$sort_code}" name="order_by" {if $sort_code eq $sort_order_checked}checked="checked"{/if}> {$sort_label}</label></p>
		{/foreach}

		<p>
		    <label><input type="checkbox" name="recursive"> <strong>{'Apply to sub-albums'|translate}</strong></label>
		</p>

		<p>
		    <input type="hidden" name="pwg_token" value="{$csrf_token}">
		    <input class="btn btn-submit" name="submitAutoOrder" type="submit" value="{'Save order'|translate}">
		    <a href="#apply-automatic-sort-order" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
	    </div>
	</form>
    {/if}

    <form id="categoryOrdering" action="{$F_ACTION_UPDATE}" method="post">
	<p id="manualOrder" class="collapse">
	    <input type="hidden" name="pwg_token" value="{$csrf_token}">
	    <input class="btn btn-submit3" name="submitManualOrder" type="submit" value="{'Save manual order'|translate}">
	    ... {'or'|translate} <a href="#manualOrder" class="btn btn-cancel" data-toggle="collapse">{'cancel manual order'|translate}</a>
	</p>

	{if count($categories)}
	    <div class="albums">
		{foreach $categories as $category}
		    {if $category.IS_PRIVATE}
			{assign var="status_icon" value="fa-lock"}
			{assign var="status_title" value="{'Private album'|translate}"}
		    {else}
			{assign var="status_icon" value="fa-unlock"}
			{assign var="status_title" value="{'Public album'|translate}"}
		    {/if}

		    <div class="album{if $category.IS_VIRTUAL} virtual_cat{/if}" id="cat_{$category.ID}">
			<p class="album-title">
			    <i class="fa infos {$status_icon}" title="{$status_title}"></i>
			    <i class="drag_button move visibility-hidden" title="{'Drag to re-order'|translate}"></i>
			    <strong><a href="{$category.U_CHILDREN}" title="{'manage sub-albums'|translate}">{$category.NAME}</a></strong>
			    <span class="albumInfos">
				<span class="userSeparator">&middot;</span>
				{'number_of_photos'|translate:['count' => $category.NB_PHOTOS]}
				<span class="userSeparator">&middot;</span>
				{'number_of_photos'|translate:['count' => $category.NB_SUB_PHOTOS]}
				{'number_of_photos_in_sub_albums'|translate:['count' => $category.NB_SUB_ALBUMS]}
			    </span>
			</p>

			<input type="hidden" name="catOrd[{$category.ID}]" value="{$category.RANK}">

			<p class="album-actions">
			    <a class="btn btn-sm btn-edit" href="{$category.U_EDIT}"><i class="fa fa-pencil"></i>{'Edit'|translate}</a>
			    <a class="btn btn-sm btn-info" href="{$category.U_CHILDREN}"><i class="fa fa-sitemap"></i>{'manage sub-albums'|translate}</a>
			    {if isset($category.U_SYNC) }
				<a href="{$category.U_SYNC}"><i class="fa fa-exchange"></i>{'Synchronize'|translate}</a>
			    {/if}
			    {if isset($category.U_DELETE) }
				<a class="btn btn-sm btn-danger" href="{$category.U_DELETE}" onclick="return confirm('{'Are you sure?'|translate|escape:javascript}');">
				    <i class="fa fa-trash"></i>{'delete album'|translate}
				</a>
			    {/if}
			    <a class="btn btn-sm btn-warning" href="{$category.U_JUMPTO}"><i class="fa fa-eye"></i> {'jump to album'|translate}</a>
			</p>
		    </div>
		{/foreach}
	    </div>
	{/if}
    </form>
{/block}
