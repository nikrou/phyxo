<div class="double-select">
    <div class="form-select">
      <label for="cat-true">{$L_CAT_OPTIONS_TRUE}</label>
      <select class="custom-select" id="cat-true" name="cat_true[]" multiple="multiple">
        {html_options options=$category_option_true selected=$category_option_true_selected}
      </select>
      <p><input class="btn btn-sm btn-submit" type="submit" value="&raquo;" name="falsify"></p>
    </div>

    <div class="form-select">
      <label for="cat-false">{$L_CAT_OPTIONS_FALSE}</label>
      <select class="custom-select" id="cat-false" name="cat_false[]" multiple="multiple">
        {html_options options=$category_option_false selected=$category_option_false_selected}
      </select>
      <p><input class="btn btn-sm btn-submit" type="submit" value="&laquo;" name="trueify"></p>
    </div>
</div>
