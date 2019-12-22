<div id="infopanel" class="col-lg-8 col-md-10 col-12 mx-auto">
    <!-- Nav tabs -->
    <ul class="nav nav-tabs nav-justified flex-column flex-sm-row" role="tablist">
	{if $theme_config->picture_info == 'tabs' || ($theme_config->picture_info != 'disabled')}
            <li class="nav-item"><a class="flex-sm-fill text-sm-center nav-link active" href="#tab_info" aria-controls="tab_info" role="tab" data-toggle="tab">{'Information'|translate}</a></li>
	    {if isset($metadata)}
		<li class="nav-item"><a class="flex-sm-fill text-sm-center nav-link" href="#tab_metadata" aria-controls="tab_metadata" role="tab" data-toggle="tab">{'EXIF Metadata'|translate}</a></li>
	    {/if}
	{/if}
	{if isset($comment_add) || $COMMENT_COUNT > 0}
            <li class="nav-item{if $theme_config->picture_info == 'disabled' || ($theme_config->picture_info != 'tabs')} active{/if}"><a class="flex-sm-fill text-sm-center nav-link" href="#tab_comments" aria-controls="tab_comments" role="tab" data-toggle="tab">{'Comments'|translate} <span class="badge badge-secondary">{$COMMENT_COUNT}</span></a></li>
	{/if}
    </ul>

    <!-- Tab panes -->
    <div class="tab-content d-flex justify-content-center">
	{if $theme_config->picture_info === 'tabs' || ($theme_config->picture_info != 'disabled')}
            <div role="tabpanel" class="tab-pane active" id="tab_info">
		<div id="info-content" class="info">
		    <div class="table-responsive">
			<table class="table table-sm">
			    <colgroup>
				<col class="w-50">
				<col class="w-50">
			    </colgroup>
			    <tbody>
				{if $display_info.author and isset($INFO_AUTHOR)}
				    <tr>
					<th scope="row">{'Author'|translate}</th>
					<td><div id="Author" class="imageInfo">{$INFO_AUTHOR}</div></td>
				    </tr>
				{/if}
				{if isset($CR_INFO_NAME) && !empty($CR_INFO_NAME)}
				    <tr>
					<th scope="row">{'Copyright'|translate}</th>
					<td><div id="Copyright" class="imageInfo">{if isset($CR_INFO_URL)}<a href="{$CR_INFO_URL}">{$CR_INFO_NAME}</a>{else}{$CR_INFO_NAME}{/if}</div></td>
				    </tr>
				{/if}
				{if $display_info.rating_score and isset($rate_summary)}
				    <tr>
					<th scope="row">{'Rating score'|translate}</th>
					<td>
					    <div id="Average" class="imageInfo">
						<span id="ratingScore">
						    {if $rate_summary.count}
							{$rate_summary.score}
						    {else}
							{'no rate'|translate}
						    {/if}
						</span>
						<span id="ratingCount">
						    {if $rate_summary.count}({'number_of_rates'|translate:['count' => $rate_summary.count]}){/if}
						</span>
					    </div>
					</td>
				    </tr>
				{/if}
				{if isset($rating)}
				    <tr>
					<th scope="row" id="updateRate">{if isset($rating.USER_RATE)}{'Update your rating'|translate}{else}{'Rate this photo'|translate}{/if}</th>
					<td>
					    <div id="rating" class="imageInfo">
						<form action="{$rating.F_ACTION}" method="post" id="rateForm">
						    {foreach $rating.marks|array_reverse as $mark}
							<input type="radio" id="rating-{$mark}" name="rating"
							       value="{$mark}" {if isset($rating.USER_RATE) && $mark==$rating.USER_RATE}checked="checked"{/if}/>
							<label for="rating-{$mark}" title="{$mark}"></label>
						    {/foreach}
						</form>
					    </div>
					</td>
				    </tr>
				{/if}
				{if $display_info.created_on and isset($INFO_CREATION_DATE)}
				    <tr>
					<th scope="row">{'Created on'|translate}</th>
					<td><div id="datecreate" class="imageInfo"><a href="{$INFO_CREATION_DATE.url}">{$INFO_CREATION_DATE.label}</a></div></td>
				    </tr>
				{/if}
				{if $display_info.posted_on}
				    <tr>
					<th scope="row">{'Posted on'|translate}</th>
					<td><div id="datepost" class="imageInfo"><a href="{$INFO_POSTED_DATE.url}">{$INFO_POSTED_DATE.label}</a></div></td>
				    </tr>
				{/if}
				{if $display_info.visits}
				    <tr>
					<th scope="row">{'Visits'|translate}</th>
					<td><div id="visits" class="imageInfo">{$INFO_VISITS}</div></td>
				    </tr>
				{/if}
				{if $display_info.dimensions and isset($INFO_DIMENSIONS)}
				    <tr>
					<th scope="row">{'Dimensions'|translate}</th>
					<td><div id="Dimensions" class="imageInfo">{$INFO_DIMENSIONS}</div></td>
				    </tr>
				{/if}
				{if $display_info.file}
				    <tr>
					<th scope="row">{'File'|translate}</th>
					<td><div id="File" class="imageInfo">{$INFO_FILE}</div></td>
				    </tr>
				{/if}
				{if $display_info.filesize and isset($INFO_FILESIZE)}
				    <tr>
					<th scope="row">{'Filesize'|translate}</th>
					<td><div id="Filesize" class="imageInfo">{$INFO_FILESIZE}</div></td>
				    </tr>
				{/if}
				{if $display_info.tags}
				    <tr>
					<th scope="row">
					    {if $TAGS_PERMISSION_ADD}<i class="fa fa-edit edit-tags"></i>{/if}{'Tags'|translate}
					</th>
					<td>
					    {include file="_picture_tags.tpl"}
					</td>
				    </tr>
				{/if}
				{if $display_info.categories and isset($related_categories)}
				    <tr>
					<th scope="row">{'Albums'|translate}</th>
					<td>
					    <div id="Categories" class="imageInfo">
						{foreach $related_categories as $cat}
						    {if !$cat@first}, {/if}{$cat}
						{/foreach}
					    </div>
					</td>
				    </tr>
				{/if}
				{if $display_info.privacy_level and isset($available_permission_levels)}
				    <tr>
					<th scope="row">{'Who can see this photo?'|translate}</dt>
					    <td>
						<div id="Privacy" class="imageInfo">
						    <div class="dropdown">
							<button class="btn btn-secondary btn-raised dropdown-toggle ellipsis" type="button" id="dropdownPermissions" data-toggle="dropdown" aria-expanded="true">
							    {$available_permission_levels[$current.level]}
							</button>
							<div class="dropdown-menu" role="menu" aria-labelledby="dropdownPermissions">
							    {foreach $available_permission_levels as $level => $label}
								<button id="permission-{$level}" type="button" class="dropdown-item permission-li {if $current.level == $level} active{/if}"
									    data-action="setPrivacyLevel" data-id="{$current.id}" data-level="{$level}" data-label="{$label}">{$label}</button>
							    {/foreach}
							</div>
						    </div>
						</div>
					    </td>
				    </tr>
				{/if}
			    </tbody>
			</table>
		    </div>
		</div>
            </div>

            <!-- metadata -->
	    {if isset($metadata)}
		<div role="tabpanel" class="tab-pane" id="tab_metadata">
		    <div id="metadata" class="info">
			<div class="table-responsive">
			    <table class="table table-sm">
				<colgroup>
				    <col class="w-50">
				    <col class="w-50">
				</colgroup>
				<tbody>
				    {foreach $metadata as $meta}
					{foreach $meta.lines as $label => $value}
					    <tr>
						<th scope="row">{$label}</th>
						<td>{$value}</td>
					    </tr>
					{/foreach}
				    {/foreach}
				</tbody>
			    </table>
			</div>
		    </div>
		</div>
	    {/if}
	{/if}

        <!-- comments -->
	{if isset($comment_add) || $COMMENT_COUNT > 0}
            <div role="tabpanel" class="tab-pane" id="tab_comments">
		{include file='picture_info_comments.tpl'}
            </div>
	{/if}
    </div>
</div>
