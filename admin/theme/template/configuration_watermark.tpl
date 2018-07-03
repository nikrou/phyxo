{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Configuration'|translate}</a></li>
    <li class="breadcrumb-item">{'Watermark'|translate}</li>
{/block}

{block name="content"}
    {combine_script id='common' load='footer' path='admin/theme/js/common.js'}

    {footer_script}
    (function(){
    function onWatermarkChange() {
    var val = jQuery("#wSelect").val();
    if (val.length) {
    jQuery("#wImg").attr('src', '{$ROOT_URL}'+val).show();
    }
    else {
    jQuery("#wImg").hide();
    }
    }

    onWatermarkChange();

    jQuery("#wSelect").bind("change", onWatermarkChange);

    if (jQuery("input[name='w[position]']:checked").val() == 'custom') {
    jQuery("#positionCustomDetails").show();
    }

    jQuery("input[name='w[position]']").change(function(){
    if (jQuery(this).val() == 'custom') {
    jQuery("#positionCustomDetails").show();
    }
    else {
    jQuery("#positionCustomDetails").hide();
    }
    });

    jQuery(".addWatermarkOpen").click(function(){
    jQuery("#addWatermark, #selectWatermark").toggle();
    return false;
    });
    }());
    {/footer_script}

    <form method="post" action="{$F_ACTION}" class="properties" enctype="multipart/form-data">
	<div class="fieldset">
	    <ul>
			<li>
		    	<span id="selectWatermark"{if isset($ferrors.watermarkImage)} style="display:none"{/if}><label>{'Select a file'|translate}</label>
				<select name="w[file]" id="wSelect" class="custom-select">
			    	{html_options options=$watermark_files selected=$watermark.file}
				</select>

				{'... or '|translate}<a href="#" class="addWatermarkOpen">{'add a new watermark'|translate}</a>
				<br>
				<img id="wImg" alt=""/>
		    	</span>

		    	<span id="addWatermark"{if isset($ferrors.watermarkImage)} style="display:inline"{/if}>
				{'add a new watermark'|translate} {'... or '|translate}<a href="#" class="addWatermarkOpen">{'Select a file'|translate}</a>

				<br>
				<input class="form-control{if isset($ferrors.watermarkImage)} dError{/if}"
				type="file" size="60" id="watermarkImage" name="watermarkImage"> (png)
				{if isset($ferrors.watermarkImage)}<span class="dErrorDesc" title="{$ferrors.watermarkImage|@htmlspecialchars}">!</span>{/if}
		    	</span>
			</li>
			<li>
		    	<label>
				{'Apply watermark if width is bigger than'|translate}
				<input class="form-control{if isset($ferrors.watermark.minw)} dError{/if}" 
				size="4" maxlength="4" type="text" name="w[minw]" value="{$watermark.minw}">
		    	</label>
		    	{'pixels'|translate}
			</li>
			<li>
		    	<label>
				{'Apply watermark if height is bigger than'|translate}
				<input class="form-control{if isset($ferrors.watermark.minw)} dError{/if}" 
				size="4" maxlength="4" type="text" name="w[minh]" value="{$watermark.minh}">
		    	</label>
		    	{'pixels'|translate}
			</li>
			<li>
		    	<label>{'Position'|translate}</label>
		    	<br>
		    	<div id="watermarkPositionBox">
					<label class="right">{'top right corner'|translate} <input name="w[position]" type="radio" value="topright"{if $watermark.position eq 'topright'} checked="checked"{/if}></label>
					<label><input name="w[position]" type="radio" value="topleft"{if $watermark.position eq 'topleft'} checked="checked"{/if}> {'top left corner'|translate}</label>
					<label class="middle"><input name="w[position]" type="radio" value="middle"{if $watermark.position eq 'middle'} checked="checked"{/if}> {'middle'|translate}</label>
					<label class="right">{'bottom right corner'|translate} <input name="w[position]" type="radio" value="bottomright"{if $watermark.position eq 'bottomright'} checked="checked"{/if}></label>
					<label><input name="w[position]" type="radio" value="bottomleft"{if $watermark.position eq 'bottomleft'} checked="checked"{/if}> {'bottom left corner'|translate}</label>
				</div>

			    <label style="display:block;margin-top:10px;font-weight:normal;"><input name="w[position]" type="radio" value="custom"{if $watermark.position eq 'custom'} checked="checked"{/if}> {'custom'|translate}</label>

			    <div id="positionCustomDetails">
				<label>{'X Position'|translate}
				    <input class="form-control{if isset($ferrors.watermark.xpos)} dError{/if}" 
					size="3" maxlength="3" type="text" name="w[xpos]" value="{$watermark.xpos}">%
			    	{if isset($ferrors.watermark.xpos)}<span class="dErrorDesc" title="{$ferrors.watermark.xpos}">!</span>{/if}
				</label>
				<br>
				<label>{'Y Position'|translate}
			    	<input class="form-control{if isset($ferrors.watermark.ypos)} dError{/if}" 
					size="3" maxlength="3" type="text" name="w[ypos]" value="{$watermark.ypos}">%
			    	{if isset($ferrors.watermark.ypos)}<span class="dErrorDesc" title="{$ferrors.watermark.ypos}">!</span>{/if}
				</label>
				<br>
				<label>{'X Repeat'|translate}
			    	<input class="form-control{if isset($ferrors.watermark.xrepeat)} dError{/if}" 
					size="3" maxlength="3" type="text" name="w[xrepeat]" value="{$watermark.xrepeat}">
			    	{if isset($ferrors.watermark.xrepeat)}<span class="dErrorDesc" title="{$ferrors.watermark.xrepeat}">!</span>{/if}
				</label>
		    	</div>
			</li>
			<li>
		    	<label>{'Opacity'|translate}</label>
		    	<input class="form-control{if isset($ferrors.watermark.opacity)} dError{/if}"
			 	size="3" maxlength="3" type="text" name="w[opacity]" value="{$watermark.opacity}"> %
		    	{if isset($ferrors.watermark.opacity)}<span class="dErrorDesc" title="{$ferrors.watermark.opacity}">!</span>{/if}
			</li>
	    </ul>
	</div>

	<p>
	    <input type="submit" class="btn btn-submit" name="submit" value="{'Save Settings'|translate}">
	</p>

    </form>
{/block}
