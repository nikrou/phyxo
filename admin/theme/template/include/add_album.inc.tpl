<div class="collapse">
    <div id="addAlbumForm">
	<form action="">
	    <p>
		<label>{'Parent album'|translate}
		    <select name="category_parent">
			<option></option>
		    </select>
		</label>
	    </p>

	    <p>
		<label>{'Album name'|translate}
		    <input class="form-control" name="category_name" type="text" maxlength="255">
		</label>
		<span id="categoryNameError"></span>
	    </p>

	    <p>
		<input class="btn btn-submit" type="submit" value="{'Create'|translate}">
		<span id="albumCreationLoading" style="display:none"></span>
	    </p>
	</form>
    </div>
</div>
