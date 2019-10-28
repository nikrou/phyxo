{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album'|translate}:
	{foreach $CATEGORIES_NAV as $category_nav}
	    <a href="{$category_nav.url}">{$category_nav.name}</a>
	    {if !$category_nav@last}/{/if}
	{/foreach}
    </li>
    <li class="breadcrumb-item">{'Manage photo ranks'|translate}</li>
{/block}

{block name="footer_assets" prepend}
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.core.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.widget.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.mouse.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/ui/jquery.ui.sortable.js"></script>
    <script src="{$ROOT_URL}admin/theme/js/element_set_ranks.js"></script>
{/block}

{block name="content"}
    <form action="{$F_ACTION}" method="post">
	{if !empty($thumbnails)}
	    <p><input class="btn btn-submit" type="submit" value="{'Submit'|translate}" name="submit"></p>
	    <div class="fieldset">
		<h3>{'Manual order'|translate}</h3>
		<p>{'Drag to re-order'|translate}</p>
		<ul class="thumbnails sort-order">
		    {foreach from=$thumbnails item=thumbnail}
			<li class="rank-of-image">
			    <img src="{$thumbnail.TN_SRC}" class="thumbnail" alt="{$thumbnail.NAME|@replace:'"':' '}" title="{$thumbnail.NAME|@replace:'"':' '}"  style="width:{$thumbnail.SIZE[0]}px; height:{$thumbnail.SIZE[1]}px; ">
			    <input type="text" name="rank_of_image[{$thumbnail.ID}]" value="{$thumbnail.RANK}" style="display:none">
			</li>
		    {/foreach}
		</ul>
	    </div>
	{/if}

	<div class="fieldset">
	    <h3>{'Sort order'|translate}</h3>
	    <p>
		<input type="radio" name="image_order_choice" id="image_order_default" value="default"{if $image_order_choice=='default'} checked="checked"{/if}>
		<label for="image_order_default">{'Use the default photo sort order'|translate}</label>
	    </p>

	    <p>
		<input type="radio" name="image_order_choice" id="image_order_rank" value="rank"{if $image_order_choice=='rank'} checked="checked"{/if}>
		<label for="image_order_rank">{'manual order'|translate}</label>
	    </p>

	    <p>
		<input type="radio" name="image_order_choice" id="image_order_user_define" value="user_define"{if $image_order_choice=='user_define'} checked="checked"{/if}>
		<label for="image_order_user_define">{'automatic order'|translate}</label>
	    </p>

	    <div id="image_order_user_define_options">
		{foreach $image_order as $order}
		    <p>
			<select class="custom-select" name="image_order[]">
			    {html_options options=$image_order_options selected=$order}
			</select>
		    </p>
		{/foreach}
		</div>
	</div>

	<p>
	    <input class="btn btn-submit" type="submit" value="{'Submit'|translate}" name="submit">

	    <label>
			<input type="checkbox" name="image_order_subcats" id="image_order_subcats">
			{'Apply to sub-albums'|translate}
	    </label>
	</p>
    </form>
{/block}
