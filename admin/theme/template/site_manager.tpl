{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item">{'Site manager'|translate}</li>
{/block}

{block name="content"}
    {if !empty($remote_output)}
	<div class="remoteOutput">
	    <ul>
		{foreach $remote_output as $remote_line}
		    <li class="{$remote_line.CLASS}">{$remote_line.CONTENT}</li>
		{/foreach}
	    </ul>
	</div>
    {/if}

    {if !empty($sites)}
	<table class="table table-hover table-striped">
	    <thead>
		<tr>
		    <td>{'Directory'|translate}</td>
		    <td>{'Actions'|translate}</td>
		</tr>
	    </thead>
	    <tbody>
		{foreach $sites as $site}
		    <tr>
			<td>
		    	    <p>{$site.NAME}</p>
			    <p>({$site.TYPE}, {$site.CATEGORIES} {'Albums'|translate}, {'number_of_photos'|translate:['count' => $site.IMAGES]})</p>
		 	</td>
			<td>
			    <a class="btn btn-sm btn-success" href="{$site.U_SYNCHRONIZE}" title="{'update the database from files'|translate}">{'Synchronize'|translate}</a>
			    <form method="POST" action="{$F_ACTION_DELETE}" class="d-inline">
				<input type="hidden" name="site" value="{$site.ID}"/>
				<input type="hidden" name="pwg_token" value="{$csrf_token}">
				<input type="submit" class="btn btn-sm btn-delete" onClick="return confirm('{'Are you sure?'|translate|escape:'javascript'}');"
				       title="{'delete this site and all its attached elements'|translate}" value="{'delete'|translate}"/>
			    </form>
			    {if !empty($site.plugin_links)}
				{foreach $site.plugin_links as $plugin_link}
				    <a class="btn btn-sm btm-submit" href="{$plugin_link.U_HREF}" title='{$plugin_link.U_HINT}'>{$plugin_link.U_CAPTION}</a>
				{/foreach}
			    {/if}
			</td>
		    </tr>
		{/foreach}
	    </tbody>
	</table>
    {/if}

    <p>
	<a class="btn btn-submit" data-toggle="collapse" href="#create-site">{'create a new site'|translate}</a>
    </p>

    <div id="create-site" class="collapse">
    	<form action="{$F_ACTION}" method="post">
	    <div class="fieldset">
		<h3>{'create a new site'|translate}</h3>

		<p>
		    <label>{'Directory'|translate}
			<input type="hidden" name="pwg_token" value="{$csrf_token}">
			<input type="text" class="form-control" name="galleries_url" id="galleries_url">
		    </label>
	    	</p>

	    	<p>
		    <input class="btn btn-submit" type="submit" name="submit" value="{'Submit'|translate}">
		    <a class="btn btn-cancel" href="#create-site" data-toggle="collapse">{'Cancel'|translate}</a>
	    	</p>
	    </div>
    	</form>
    </div>
{/block}
