{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Configuration'|translate}</a></li>
    <li class="breadcrumb-item">{'Photo sizes'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='common' load='footer' path='admin/theme/js/common.js'}

    <form method="post" action="{$F_ACTION}" class="properties">
	<div class="fieldset">
	    <h3>{'Original Size'|translate}</h3>
	    {if $is_gd}
		    <div>
			{'Resize after upload disabled due to the use of GD as graphic library'|translate}
			<input type="checkbox" name="original_resize"disabled="disabled" style="visibility: hidden">
			<input type="hidden" name="original_resize_maxwidth" value="{$sizes.original_resize_maxwidth}">
			<input type="hidden" name="original_resize_maxheight" value="{$sizes.original_resize_maxheight}">
			<input type="hidden" name="original_resize_quality" value="{$sizes.original_resize_quality}">
		    </div>
		{else}
		    <div>
			<label class="font-checkbox">
			    <i class="fa fa-check-square"></i>
			    <input type="checkbox" name="original_resize" {if ($sizes.original_resize)}checked="checked"{/if} data-toggle="collapse" data-target="#resize-original">
			    {'Resize after upload'|translate}
			</label>
		    </div>

		    <div class="collapse" id="resize-original">
			<p>
			    <label>
				{'Maximum width'|translate}
				<input type="text" name="original_resize_maxwidth" value="{$sizes.original_resize_maxwidth}" size="4" maxlength="4"{if isset($ferrors.original_resize_maxwidth)} class="dError"{/if}>
				{'pixels'|translate}
				{if isset($ferrors.original_resize_maxwidth)}<span class="dErrorDesc" title="{$ferrors.original_resize_maxwidth}">!</span>{/if}
			    </label>
			</p>
			<p>
			    <label>
				{'Maximum height'|translate}
				<input type="text" name="original_resize_maxheight" value="{$sizes.original_resize_maxheight}" size="4" maxlength="4"{if isset($ferrors.original_resize_maxheight)} class="dError"{/if}>
				{'pixels'|translate}
				{if isset($ferrors.original_resize_maxheight)}<span class="dErrorDesc" title="{$ferrors.original_resize_maxheight}">!</span>{/if}
			    </label>
			</p>
			<p>
			    <label>
				{'Image Quality'|translate}
				<input type="text" name="original_resize_quality" value="{$sizes.original_resize_quality}" size="3" maxlength="3"{if isset($ferrors.original_resize_quality)} class="dError"{/if}> %
				{if isset($ferrors.original_resize_quality)}<span class="dErrorDesc" title="{$ferrors.original_resize_quality}">!</span>{/if}
			    </label>
			</p>
		    </div>
		{/if}
	</div>

	<div class="fieldset">
	    <h3>{'Multiple Size'|translate}</h3>

	    <table class="table table-hover">
		{foreach from=$derivatives item=d key=type}
		    <tr>
			<td>
			    <label>
				{if $d.must_enable}
				    <span class="sizeEnable">
					<i class="fa fa-check"></i>
				    </span>
				{else}
				    <label class="font-checkbox">
					<i class="fa fa-check-square"></i>
					<input type="checkbox" name="d[{$type}][enabled]" {if $d.enabled}checked="checked"{/if}>
				    </label>
				{/if}
				{$type|translate}
			    </label>
			</td>

			<td>
			    {$d.w} x {$d.h} {'pixels'|translate}{if $d.crop}, {'Crop'|translate|lower}{/if}
			</td>

			<td>
			    <a class="btn btn-submit" href="#sizeEdit-{$type}" data-toggle="collapse">{'edit'|translate}</a>
			</td>
		    </tr>

		    <tr id="sizeEdit-{$type}" class="collapse">
			<td colspan="3">
			    {if !$d.must_square}
				<p>
				    <label class="font-checkbox">
					<i class="fa fa-check-square"></i>
					<input type="checkbox" class="cropToggle" name="d[{$type}][crop]" {if $d.crop}checked="checked"{/if}>
					{'Crop'|translate}
				    </label>
				</p>
			    {/if}
			    <p>
				<label>
				    {if $d.must_square or $d.crop}{'Width'|translate}{else}{'Maximum width'|translate}{/if}
				    <input class="form-control" type="text" name="d[{$type}][w]" maxlength="4" size="4" value="{$d.w}"{if isset($ferrors.$type.w)} class="dError"{/if}> {'pixels'|translate}
				    {if isset($ferrors.$type.w)}<span class="dErrorDesc" title="{$ferrors.$type.w}">!</span>{/if}
				</label>
			    </p>
			    {if !$d.must_square}
				<p>
				    <label>{if $d.crop}{'Height'|translate}{else}{'Maximum height'|translate}{/if}
					<input class="form-control" type="text" name="d[{$type}][h]" maxlength="4" size="4"  value="{$d.h}"{if isset($ferrors.$type.h)} class="dError"{/if}> {'pixels'|translate}
					{if isset($ferrors.$type.h)}<span class="dErrorDesc" title="{$ferrors.$type.h}">!</span>{/if}
				    </label>
				</p>
			    {/if}
			    <p>
				<label>
				    {'Sharpen'|translate}
				    <input class="form-control" type="text" name="d[{$type}][sharpen]" maxlength="4" size="4"  value="{$d.sharpen}"{if isset($ferrors.$type.sharpen)} class="dError"{/if}> %
				    {if isset($ferrors.$type.sharpen)}<span class="dErrorDesc" title="{$ferrors.$type.sharpen}">!</span>{/if}
				</label>
			    </p>
			</td>
		    </tr>
		{/foreach}
	    </table>

	    <p>
		{'Image Quality'|translate}
		<input class="form-control" type="text" name="resize_quality" value="{$resize_quality}" size="3" maxlength="3"{if isset($ferrors.resize_quality)} class="dError"{/if}> %
		{if isset($ferrors.resize_quality)}<span class="dErrorDesc" title="{$ferrors.resize_quality}">!</span>{/if}
	    </p>
	    <p>
			<a class="btn btn-reset" href="{$F_ACTION}&amp;action=restore_settings" onclick="return confirm('{'Are you sure?'|translate|@escape:javascript}');">{'Reset to default values'|translate}</a>
	    </p>
	</div>

	{if !empty($custom_derivatives)}
	    <div class="fieldset">
		<h3>{'custom'|translate}</h3>

		{foreach from=$custom_derivatives item=time key=custom}
		    <p>
			<label class="font-checkbox">
			    <i class="fa fa-check-square"></i>
			    <input type="checkbox" name="delete_custom_derivative_{$custom}"> {'Delete'|translate} {$custom} ({'Last hit'|translate}: {$time})
			</label>
		    </p>
		{/foreach}
	    </div>
	{/if}

	<p>
	    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
	    <input type="submit" class="btn btn-submit" name="submit" value="{'Save Settings'|translate}">
	</p>
    </form>
{/block}
