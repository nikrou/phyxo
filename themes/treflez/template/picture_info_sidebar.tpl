<div id="sidebar">
    <div id="info-content" class="info">
        <dl id="standard" class="imageInfoTable">
            <h4>{'Information'|translate}</h4>
	    {if $display_info.author and isset($INFO_AUTHOR)}
		<div id="Author" class="imageInfo">
                    <dt>{'Author'|translate}</dt>
                    <dd>{$INFO_AUTHOR}</dd>
		</div>
	    {/if}
	    {if isset($CR_INFO_NAME) && !empty($CR_INFO_NAME)}
		<div id="Copyright" class="imageInfo">
                    <dt>{'Copyright'|translate}</dt>
                    <dd>{if isset($CR_INFO_URL)}<a href="{$CR_INFO_URL}">{$CR_INFO_NAME}</a>{else}{$CR_INFO_NAME}{/if}</dd>
		</div>
	    {/if}
	    {if $display_info.created_on and isset($INFO_CREATION_DATE)}
		<div id="datecreate" class="imageInfo">
                    <dt>{'Created on'|translate}</dt>
                    <dd>{$INFO_CREATION_DATE}</dd>
		</div>
	    {/if}
	    {if $display_info.posted_on}
		<div id="datepost" class="imageInfo">
                    <dt>{'Posted on'|translate}</dt>
                    <dd>{$INFO_POSTED_DATE}</dd>
		</div>
	    {/if}
	    {if $display_info.visits}
		<div id="visits" class="imageInfo">
                    <dt>{'Visits'|translate}</dt>
                    <dd>{$INFO_VISITS}</dd>
		</div>
	    {/if}
	    {if $display_info.dimensions and isset($INFO_DIMENSIONS)}
		<div id="Dimensions" class="imageInfo">
                    <dt>{'Dimensions'|translate}</dt>
                    <dd>{$INFO_DIMENSIONS}</dd>
		</div>
	    {/if}
	    {if $display_info.file}
		<div id="File" class="imageInfo">
                    <dt>{'File'|translate}</dt>
                    <dd>{$INFO_FILE}</dd>
		</div>
	    {/if}
	    {if $display_info.filesize and isset($INFO_FILESIZE)}
		<div id="Filesize" class="imageInfo">
                    <dt>{'Filesize'|translate}</dt>
                    <dd>{$INFO_FILESIZE}</dd>
		</div>
	    {/if}
	    {if $display_info.tags and isset($related_tags)}
		<div id="Tags" class="imageInfo">
                    <dt>{'Tags'|translate}</dt>
                    <dd>
			{foreach $related_tags as $tag}{if !$tag@first}, {/if}<a href="{$tag.URL}">{$tag.name}</a>{/foreach}
                    </dd>
		</div>
	    {/if}
	    {if $display_info.categories and isset($related_categories)}
		<div id="Categories" class="imageInfo">
		    <dt>{'Albums'|translate}</dt>
                    <dd>
			{foreach $related_categories as $cat}
			    {if !$cat@first}<br />{/if}{$cat}
			{/foreach}
                    </dd>
		</div>
	    {/if}
	    {if $display_info.rating_score and isset($rate_summary)}
		<div id="Average" class="imageInfo">
                    <dt>{'Rating score'|translate}</dt>
                    <dd>
			{if $rate_summary.count}
                            <span id="ratingScore">{$rate_summary.score}</span> <span id="ratingCount">({$rate_summary.count|translate_dec:'%d rate':'%d rates'})</span>
			{else}
                            <span id="ratingScore">{'no rate'|translate}</span> <span id="ratingCount"></span>
			{/if}
                    </dd>
		</div>
	    {/if}

	    {if isset($rating)}
		<div id="rating" class="imageInfo">
                    <dt id="updateRate">{if isset($rating.USER_RATE)}{'Update your rating'|translate}{else}{'Rate this photo'|translate}{/if}</dt>
                    <dd>
                        <form action="{$rating.F_ACTION}" method="post" id="rateForm" style="margin:0;">
                            <div>
				{foreach $rating.marks as $mark}
				    {if isset($rating.USER_RATE) && $mark==$rating.USER_RATE}
					<span class="rateButtonStarFull" data-value="{$mark}"></span>
				    {else}
					<span class="rateButtonStarEmpty" data-value="{$mark}"></span>
				    {/if}
				{/foreach}
                            </div>
                        </form>
                    </dd>
		</div>
	    {/if}
	    {if $display_info.privacy_level and isset($available_permission_levels)}
		<div id="Privacy" class="imageInfo">
                    <dt>{'Who can see this photo?'|translate}</dt>
                    <dd>
			<div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle ellipsis" type="button" id="dropdownPermissions" data-toggle="dropdown" aria-expanded="true">
				{$available_permission_levels[$current.level]}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" role="menu" aria-labelledby="dropdownPermissions">
				{foreach $available_permission_levels as $level => $label}
				    <a id="permission-{$level}" class="dropdown-item permission-li {if $current.level == $level} active{/if}" href="javascript:setPrivacyLevel({$current.id},{$level},'{$label}')">{$label}</a>
				{/foreach}
                            </div>
			</div>
                    </dd>
		</div>
	    {/if}
	    {if isset($metadata)}
		{foreach $metadata as $meta}
		    <h4>{$meta.TITLE}</h4>
		    <div>
			<dl class="row mb-0">
			    {foreach $meta.lines as $label => $value}
				<dt>{$label}</dt>
				<dd>{$value}</dd>
			    {/foreach}
			</dl>
		    </div>
		{/foreach}
	    {/if}
        </dl>
    </div>
    <div class="handle">
        <button type="button" id="info-link" class="btn">
            <i class="fa fa-info" aria-hidden="true"></i>
	</button>
    </div>
</div>
