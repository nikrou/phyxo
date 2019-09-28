{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Configuration'|translate}</a></li>
    <li class="breadcrumb-item">{'Watermark'|translate}</li>
{/block}

{block name="content"}
    <form method="post" action="{$F_ACTION}" class="watermark" enctype="multipart/form-data">
	<div class="fieldset">
	    <div class="form-row align-items-center" id="new-watermark">
		<div class="col-auto">
		    <label class="col-form-label">{'Select a file'|translate}</label>
		</div>
		<div class="col-auto">
		    <select name="watermark[file]" class="custom-select">
			{html_options options=$watermark_files selected=$watermark.file}
		    </select>
		    <img style="max-width:100px" id="seleted-watermark-file" src="{$ROOT_URL}/{$watermark.file}" alt=""/>
		</div>
		<div class="col-auto">
		    {'... or '|translate}
		</div>
		<div class="col-auto">
		    <button type="button" id="addWatermark" class="btn btn-submit">{'add a new watermark'|translate}</button>
		</div>
	    </div>

	    <div class="form-row align-items-center d-none" id="add-watermark">
		<div class="col-auto">
		    <div class="custom-file" id="new-watermark">
			<input class="custom-file-input" type="file" size="60" id="watermarkImage" name="watermarkImage">
			<label class="custom-file-label" for="watermarkImage" data-browse="{'Browse'|translate}" for="customFile">{'Choose a file'|translate} (png)</label>
		    </div>
		</div>
		<div class="col-auto">
		    {'... or '|translate}
		</div>
		<div class="col-auto">
		    <button type="button" class="btn btn-submit" id="newWatermark">{'Select a file'|translate}</button>
		</div>
	    </div>

	    <div>
		<label>
		    {'Apply watermark if width is bigger than'|translate}
		    <input class="form-control{if isset($ferrors.watermark.minw)} dError{/if}"
			   size="4" maxlength="4" type="text" name="watermark[minw]" value="{$watermark.minw}">
		</label>
		{'pixels'|translate}
	    </div>

	    <div>
		<label>
		    {'Apply watermark if height is bigger than'|translate}
		    <input class="form-control{if isset($ferrors.watermark.minw)} dError{/if}"
			   size="4" maxlength="4" type="text" name="watermark[minh]" value="{$watermark.minh}">
		</label>
		{'pixels'|translate}
	    </div>

	    <div>
		<h4>{'Position'|translate}</h4>
		<div id="watermarkPositionBox">
		    <label class="right">{'top right corner'|translate} <input name="watermark[position]" type="radio" value="topright"{if $watermark.position eq 'topright'} checked="checked"{/if}></label>
		    <label><input name="watermark[position]" type="radio" value="topleft"{if $watermark.position eq 'topleft'} checked="checked"{/if}> {'top left corner'|translate}</label>
		    <label class="middle"><input name="watermark[position]" type="radio" value="middle"{if $watermark.position eq 'middle'} checked="checked"{/if}> {'middle'|translate}</label>
		    <label class="right">{'bottom right corner'|translate} <input name="watermark[position]" type="radio" value="bottomright"{if $watermark.position eq 'bottomright'} checked="checked"{/if}></label>
		    <label><input name="watermark[position]" type="radio" value="bottomleft"{if $watermark.position eq 'bottomleft'} checked="checked"{/if}> {'bottom left corner'|translate}</label>
		</div>

		<label>
		    <input name="watermark[position]" type="radio" value="custom"{if $watermark.position eq 'custom'} checked="checked"{/if}>
		    {'custom'|translate}
		</label>

		<div class="row{if $watermark.position !== 'custom'} d-none{/if}" id="positionCustomDetails">
		    <div class="col-auto">
			<label>{'X Position'|translate}
			    <input class="form-control" size="3" maxlength="3" type="text" name="watermark[xpos]" value="{$watermark.xpos}">
			</label> %
		    </div>


		    <div class="col-auto">
			<label>{'Y Position'|translate}
			    <input class="form-control" size="3" maxlength="3" type="text" name="watermark[ypos]" value="{$watermark.ypos}">
			</label> %
		    </div>

		    <div class="col-auto">
			<label>{'X Repeat'|translate}
			    <input class="form-control" size="3" maxlength="3" type="text" name="watermark[xrepeat]" value="{$watermark.xrepeat}">
			</label>
		    </div>
		</div>
	    </div>
	    <div>
		<label>
		    {'Opacity'|translate}
		    <input class="form-control" size="3" maxlength="3" type="text" name="watermark[opacity]" value="{$watermark.opacity}">
		</label> %
	    </div>
	</div>
	<p>
	    <input type="hidden" name="pwg_token" value="{$csrf_token}">
	    <input type="submit" class="btn btn-submit" name="submit" value="{'Save Settings'|translate}">
	</p>
    </form>
{/block}
