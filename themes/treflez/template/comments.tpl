{extends file="__layout.tpl"}

{block name="head_assets" append}
    <!-- head_assets (COMMENTS) -->
    {if isset($comment_derivative_params)}
	<style type="text/css">
	 .commentElement .illustration{
	     width:{$comment_derivative_params->max_width()+5}px;
	 }

	 .content .commentElement .description{
	     min-height:{$comment_derivative_params->max_height()+5}px;
	 }
	</style>
    {/if}
    <!-- /head_assets (COMMENTS) -->
{/block}

{block name="content"}
    <nav class="navbar navbar-contextual navbar-expand-lg {$theme_config->navbar_contextual_style} {$theme_config->navbar_contextual_bg} sticky-top mb-5">
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
            <div class="navbar-brand mr-auto">
		<a href="{$U_HOME}">{'Home'|translate}</a>{$LEVEL_SEPARATOR}
		{'User comments'|translate}
	    </div>
	</div>
    </nav>

    {include file='infos_errors.tpl'}

    <div class="container{if $theme_config->fluid_width}-fluid{/if}">
	<p><a href="#filter-comments" data-toggle="collapse" class="btn btn-primary">{'Filter and display comments'|translate}</a></p>

	<div class="collapse" id="filter-comments">
	    <form action="{$F_ACTION}" method="get" class="form-horizontal row">
		<div class="card col-lg-6">
		    <h4 class="card-header">
			{'Filter'|translate}
		    </h4>
		    <div class="card-body">
			<div class="form-group">
			    <label for="keyword" class="col-sm-2 control-label">{'Keyword'|translate}</label>
			    <div class="col-sm-4">
				<input type="text" name="keyword" id="keyword" value="{$F_KEYWORD}" class="form-control" placeholder="{'Keyword'|translate}">
			    </div>
			</div>
			<div class="form-group">
			    <label for="author" class="col-sm-2 control-label">{'Author'|translate}</label>
			    <div class="col-sm-4">
				<input type="text" name="author" id="author" value="{$author}" class="form-control" placeholder="{'Author'|translate}">
			    </div>
			</div>
			<div class="form-group">
			    <label for="cat" class="col-sm-2 control-label">{'Album'|translate}</label>
			    <div class="col-sm-4">
				<select name="category" id="cat" class="form-control">
				    <option value="0">------------</option>
				    {html_options options=$categories selected=$category}
				</select>
			    </div>
			</div>
			<div class="form-group">
			    <label for="since" class="col-sm-2 control-label">{'Since'|translate}</label>
			    <div class="col-sm-4">
				<select name="since" class="form-control">
				    {html_options options=$since_options selected=$since}
				</select>
			    </div>
			</div>
		    </div>
		</div>
		<div class="card col-lg-6">
		    <h4 class="card-header">
			{'Display'|translate}
		    </h4>
		    <div class="card-body">
			<div class="form-group">
			    <label for="sort_by" class="col-sm-2 control-label">{'Sort by'|translate}</label>
			    <div class="col-sm-4">
				<select class="form-control" name="sort_by">
				    {html_options options=$sort_by_options selected=$sort_by}
				</select>
			    </div>
			</div>
			<div class="form-group">
			    <label for="sort_order" class="col-sm-2 control-label">{'Sort order'|translate}</label>
			    <div class="col-sm-4">
				<select class="form-control" name="sort_order">
				    {html_options options=$sort_order_options selected=$sort_order}
				</select>
			    </div>
			</div>
			<div class="form-group">
			    <label for="items_number" class="col-sm-2 control-label">{'Number of items'|translate}</label>
			    <div class="col-sm-4">
				<select class="form-control" name="items_number">
				    {html_options options=$items_number_options selected=$items_number}
				</select>
			    </div>
			</div>
		    </div>
		</div>
		<p class="mt-2 ml-3">
		    <input type="submit" value="{'Filter and display'|translate}" class="btn btn-primary btn-raised">
		</p>
	    </form>
	</div>
    </div>

    {if isset($comments)}
	<a name="comments"></a>
	<div class="container{if $theme_config->fluid_width}-fluid{/if} comment-search">
	    <div class="row">
		<div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1 col-sm-12 col-xs-12">
		    {include file='comment_list.tpl' comment_derivative_params=$derivative_params}
		</div>
	    </div>
	</div>
    {else}
	<div class="container{if $theme_config->fluid_width}-fluid{/if} comment-search">
	    <p>{'No comments for that search'|translate}</p>
	</div>
    {/if}

    {if !empty($navbar) }
	<div class="container{if $theme_config->fluid_width}-fluid{/if}">
	    {include file='navigation_bar.tpl' fragment='comments'}
	</div>
    {/if}
{/block}
