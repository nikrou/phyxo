{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Permalinks'|translate}</li>
{/block}

{block name="content"}
    <p><a href="#addPermalink" class="btn btn-submit" data-toggle="collapse">{'Add/delete a permalink'|translate}</a></p>

    <div id="addPermalink" class="collapse">
	<form method="post" action="">
	    <div class="fieldset">
		<h3>{'Add/delete a permalink'|translate}</h3>
		<p>
		    <label for="cat-id">{'Album'|translate}</label>
		    <select class="custom-select" name="cat_id" id="cat-id">
			<option value="0">------</option>
			{html_options options=$categories selected=$categories_selected}
		    </select>
		</p>

		<p>
		    <label for="permalink">{'Permalink'|translate}</label>
		    <input class="form-control" type="text" name="permalink" id="permalink">
		</p>

		<p>
		    <label>
			<input type="checkbox" name="save" checked="checked">
			<strong>{'Save to permalink history'|translate}</strong>
		    </label>
		</p>

		<p>
		    <input type="submit" class="btn btn-submit" name="set_permalink" value="{'Submit'|translate}">
		    <a href="#addPermalink" class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</a>
		</p>
	    </div>
	</form>
    </div>

    <div class="fieldset">
	<h3>{'Permalinks'|translate}</h3>
	<table class="table table-hover table-striped">
	    <thead>
		<tr>
		    <th>Id</th>
		    <th>{'Album'|translate}</th>
		    <th>{'Permalink'|translate}</th>
		</tr>
	    </thead>
	    <tbody>
		{foreach $permalinks as $permalink}
		    <tr>
			<td>{$permalink.id}</td>
			<td>{$permalink.name}</td>
			<td>{$permalink.permalink}</td>
		    </tr>
		{/foreach}
	    </tbody>
	</table>
    </div>

    <div class="fieldset">
	<h3>{'Permalink history'|translate} <a name="old_permalinks"></a></h3>
	<table class="table table-hover table-striped">
	    <thead>
		<tr>
		    <th>Id</th>
		    <th>{'Album'|translate}</th>
		    <th>{'Permalink'|translate}</th>
		    <th>{'Deleted on'|translate}</th>
		    <th>{'Last hit'|translate}</th>
		    <th>{'Hit'|translate}</th>
		    <th></th>
		</tr>
	    </thead>
	    <tbody>
		{foreach $deleted_permalinks as $permalink}
		    <tr>
			<td>{$permalink.cat_id}</td>
			<td>{$permalink.name}</td>
			<td>{$permalink.permalink}</td>
			<td>{$permalink.date_deleted}</td>
			<td>{$permalink.last_hit}</td>
			<td>{$permalink.hit}</td>
			<td><a href="{$permalink.U_DELETE}"><i class="fa fa-trash"></i><span class="visually-hidden">{'Delete'|translate}</span></a></td>
		    </tr>
		{/foreach}
	    </tbody>
	</table>
    </div>
{/block}
