{combine_css path="themes/treflez/admin/css/admin.css"}
{combine_script id='common' load='footer' path='admin/theme/js/common.js'}

<ul class="tabs">
    <li class="tab-link current" data-tab="appearance">{'Appearance'|translate}</li>
    <li class="tab-link" data-tab="components">{'Components'|translate}</li>
    <li class="tab-link" data-tab="social-integration">{'Social Media Integration'|translate}</li>
</ul>


<form method="post" class="properties">
    <input type="hidden" name="_settings" value="true" />
    <div id="configContent">
	<div id="appearance" class="tab-content current">
	    <div  class="fieldset">
		<h3>{'Full width layout'|translate}</h3>
		<p>
		    <label class="font-checkbox">
                        <span class="fa fa-check-square"></span>
                        <input type="checkbox" name="fluid_width"{if $theme_config->fluid_width} checked="checked"{/if}>
                        {'Enabled'|translate}
		    </label>
		    <span class="info">{'Use full width containers that span the entire width of the viewport'|translate}</span>
		</p>
                <p>
		    <label class="font-checkbox">
                        <span class="fa fa-check-square"></span>
                        <input type="checkbox" name="fluid_width_col_xxl"{if $theme_config->fluid_width_col_xxl} checked="checked"{/if}>
                        {'Use 6 colums for viewports >= 1680px'|translate}
		    </label>
		</p>
	    </div>
            <div class="fieldset">
		<h3>{'Site logo'|translate}</h3>
		<p>
		    <label class="font-checkbox">
                        <span class="fa fa-check-square"></span>
                        <input type="checkbox" name="logo_image_enabled"{if $theme_config->logo_image_enabled} checked="checked"{/if}>
                        {'Enabled'|translate}
		    </label>
		    <span class="form-text">{'Display a site logo image instead of plain text'|translate}</span>
		</p>
		<p>
		    <label>
                        {'Path'|translate}
                        <input type="text" name="logo_image_path" size="50" {if $theme_config->logo_image_path != ""}value="{$theme_config->logo_image_path}"{else}placeholder="relative/path/to/image"{/if}>
		    </label>
		    <span class="form-text">{'The path to the image, relative to your Phyxo installation folder'|translate}</span>
		</p>
	    </div>
            <div class="fieldset">
		<h3>{'Page header'|translate}</h3>
		<p>
		    <label labelfor="page_header">{'Banner style'|translate}</label>
		    <select class="custom-select" name="page_header">
                        <option value="jumbotron"{if $theme_config->page_header == 'jumbotron'} selected="selected"{/if}>{'Jumbotron'|translate}</option>
                        <option value="fancy"{if $theme_config->page_header == 'fancy'} selected="selected"{/if}>{'Hero image'|translate}</option>
                        <option value="none"{if $theme_config->page_header == 'none'} selected="selected"{/if}>{'Disabled'|translate}</option>
		    </select>
		</p>
		<p>
		    <label>
                        {'Background image'|translate}
                        <input type="text" name="page_header_image" size="50" {if $theme_config->page_header_image != ""}value="{$theme_config->page_header_image}"{else}placeholder="{'URL or releative path to the image'|translate}"{/if}>
		    </label>
		    <span class="form-text">{'URL or releative path to the image'|translate}</span>
		</p>
		<p>
		    <label class="font-checkbox">
			<span class="fa fa-check-square"></span>
			<input type="checkbox" name="page_header_both_navs"{if $theme_config->page_header_both_navs} checked=checked{/if}>
                        {'Integrate lower navbar'|translate}
		    </label>
		</p>
		<p>
		    <label class="font-checkbox">
			<span class="fa fa-check-square"></span>
			<input type="checkbox" name="page_header_full"{if $theme_config->page_header_full} checked=checked{/if}>
                        {'Span the full viewport height'|translate}
		    </label>
		</p>
            </div>
            <div class="fieldset">
		<h3>{'Category page display'|translate}</h3>
		<p>
		    <label labelfor="category_wells">{'Display categories as Bootstrap media wells'|translate}</label>
		    <select class="custom-select" name="category_wells">
                        <option value="never"{if $theme_config->category_wells == 'never'} selected="selected"{/if}>{'Never'|translate}</option>
                        <option value="always"{if $theme_config->category_wells == 'always'} selected="selected"{/if}>{'Always'|translate}</option>
                        <option value="mobile_only"{if $theme_config->category_wells == 'mobile_only'} selected="selected"{/if}>{'On mobile devices only'|translate}</option>
		    </select>
		    <span class="form-text">{'This will display categories as media wells with squared thumbnails, similar to the smartpocket mobile theme.'|translate}</span>
		</p>
		<p>
		    <label class="font-checkbox">
			<span class="fa fa-check-square"></span>
			<input type="checkbox" name="cat_descriptions"{if $theme_config->cat_descriptions} checked=checked{/if}>
			{'Display category description in grid view'|translate}
		    </label>
		</p>
		<p>
		    <label class="font-checkbox">
			<span class="fa fa-check-square"></span>
			<input type="checkbox" name="cat_nb_images"{if $theme_config->cat_nb_images} checked=checked{/if}>
			{'Display number of images in album and subalbums'|translate}
		    </label>
		</p>
            </div>
            <div class="fieldset">
		<h3>{'Thumbnail page display'|translate}</h3>
		<p>
		    <label class="font-checkbox">
                        <span class="fa fa-check-square"></span>
                        <input type="checkbox" name="thumbnail_caption"{if $theme_config->thumbnail_caption} checked="checked"{/if}>
                        {'Show image caption'|translate}
		    </label>
		</p>
		<p>
       		    <label labelfor="thumbnail_linkto">{'Link thumbnail to'|translate}</label>
		    <select class="custom-select" name="thumbnail_linkto">
                        <option value="picture"{if $theme_config->thumbnail_linkto == 'picture'} selected="selected"{/if}>{'Picture details page'|translate}</option>
                        <option value="photoswipe"{if $theme_config->thumbnail_linkto == 'photoswipe'} selected="selected"{/if}>{'PhotoSwipe Slideshow'|translate}</option>
                        <option value="photoswipe_mobile_only"{if $theme_config->thumbnail_linkto == 'photoswipe_mobile_only'} selected="selected"{/if}>{'Photoswipe Slideshow (Mobile devices only)'|translate}</option>
		    </select>
		</p>

		<h4>{'Description display style'|translate}</h4>
		<p>
                    <label class="radio" style="display: inline-block; width: 100px;">
			<input type="radio" name="thumbnail_cat_desc" value="simple"{if $theme_config->thumbnail_cat_desc == 'simple'} checked="checked"{/if} />
			{'Simple'|translate}
                    </label>
                    <span class="form-text">{'center-aligned h5 heading'|translate}</span>
		</p>
		<p>
                    <label class="radio" style="display: inline-block; width: 100px;">
			<input type="radio" name="thumbnail_cat_desc" value="advanced"{if $theme_config->thumbnail_cat_desc == 'advanced'} checked="checked"{/if} />
			{'Advanced'|translate}
                    </label>
                    <span class="form-text">{'left-aligned free text for advanced descriptions'|translate}</span>
		</p>
	    </div>
            <div class="fieldset">
		<h3>{'Picture page display'|translate}</h3>
		<p>
		    <label labelfor="picture_info">{'Picture info display position'|translate}</label>
		    <select class="custom-select" name="picture_info">
                        <option value="cards"{if $theme_config->picture_info == 'cards'} selected="selected"{/if}>{'Card grid below the image'|translate}</option>
                        <option value="tabs"{if $theme_config->picture_info == 'tabs'} selected="selected"{/if}>{'Tabs below the image'|translate}</option>
                        <option value="sidebar"{if $theme_config->picture_info == 'sidebar'} selected="selected"{/if}>{'Sidebar (like Boostrap Default)'|translate}</option>
                        <option value="disabled"{if $theme_config->picture_info == 'disabled'} selected="selected"{/if}>{'Disabled'|translate}</option>
		    </select>
		</p>
            </div>
	</div>

	<div id="components" class="tab-content">
	    <div class="fieldset">
		<h3>Slick Carousel {'Settings'|translate}</h3>
		<ul>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="slick_enabled"{if $theme_config->slick_enabled} checked="checked"{/if}>
			    {'Enabled'|translate}
			</label>
			<span class="info">{'Enable the slick carousel below the main image on the picture page'|translate}.</span>
		    </li>
		</ul>
		<ul>
		    <li>
			<label labelfor="slick_lazyload">{'lazyLoad method'|translate}</label>
			<select class="custom-select" name="slick_lazyload">
			    <option value="ondemand"{if $theme_config->slick_lazyload == 'ondemand'} selected="selected"{/if}>ondemand</option>
			    <option value="progressive"{if $theme_config->slick_lazyload == 'progressive'} selected="selected"{/if}>progressive</option>
			</select>
			<span class="info"><em>ondemand</em> {'will load the image as soon as you slide to it'|translate}. <em>progressive</em> {'loads all images one after another when the page loads (use carefully!)'|translate}.</span>
		    </li>
		</ul>
		<ul>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="slick_infinite"{if $theme_config->slick_infinite} checked="checked"{/if}>
			    {'Infinite looping'|translate}
			</label>
			<span class="info">{'Endlessly scroll through album images'|translate}</span>
		    </li>
		</ul>
		<ul>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="slick_centered"{if $theme_config->slick_centered} checked="checked"{/if}>
			    {'Center mode'|translate}
			</label>
			<span class="info">{'Display the currently selected image in the middle. Works best with infinite looping enabled.'|translate}</span>
		    </li>
		</ul>
	    </div>
	    <div class="fieldset">
		<h3>PhotoSwipe {'Settings'|translate}</h3>
		<ul>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="photoswipe"{if $theme_config->photoswipe} checked="checked"{/if}>
			    {'Enabled'|translate}
			</label>
			<span class="info">{'Enable PhotoSwipe fullscreen slideshow. Disable if you prefer to use Plugins like Fotorama or Phyxo\'s default slideshow.'|translate}</span>
		    </li>
		    <li>
			<label labelfor="photoswipe_interval">{'Autoplay interval'|translate}</label>
			<input type="number" name="photoswipe_interval" value="{$theme_config->photoswipe_interval}" min="1000" max="50000"> {'milliseconds'|translate}
		    </li>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="photoswipe_metadata"{if $theme_config->photoswipe_metadata} checked="checked"{/if}>
			    {'Show basic EXIF metadata'|translate}
			</label>
			<span class="info">{'For more information on metadata visit'|translate} <a href="https://github.com/tkuther/piwigo-bootstrap-darkroom/wiki/EXIF-Metadata-in-PhotoSwipe">Wiki:EXIF-Metadata-in-PhotoSwipe</a></span>
		    </li>
		</ul>
	    </div>
	    <div class="fieldset">
		<h3>{'Quick search'|translate}</h3>
		<ul>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="quicksearch_navbar"{if $theme_config->quicksearch_navbar} checked="checked"{/if}>
			    {'Quick search'|translate} {'directly in the navigation bar'|translate}
			</label>
		    </li>
		</ul>
	    </div>
	    <div class="fieldset">
		<h3>{'Tag cloud'|translate}</h3>
		<ul>
		    <li>
			<label class="radio">
			    <input type="radio" name="tag_cloud_type" value="basic"{if $theme_config->tag_cloud_type == 'basic'} checked="checked"{/if} />
			    {'Basic'|translate}
			</label>
			<label class="radio">
			    <input type="radio" name="tag_cloud_type" value="html5"{if $theme_config->tag_cloud_type == 'html5'} checked="checked"{/if} />
			    {'HTML 5 canvas'|translate}
			</label>
		    </li>
		</ul>
	    </div>
	</div>

	<div id="social-integration" class="tab-content">
	    <div class="fieldset">
		<h3>{'Social integration'|translate}</h3>
		<ul>
		    <li>
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_enabled"{if $theme_config->social_enabled} checked="checked"{/if}>
			    {'Enabled'|translate}
			</label>
		    </li>
		    <li id="social_twitter" class="ident">
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_twitter"{if $theme_config->social_twitter}  checked="checked"{/if}>
			    {'Twitter'|translate}
			</label>
		    </li>
		    <li id="social_facebook" class="ident">
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_facebook"{if $theme_config->social_facebook}  checked="checked"{/if}>
			    {'Facebook'|translate}
			</label>
		    </li>
		    <li id="social_google_plus" class="ident">
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_google_plus"{if $theme_config->social_google_plus}  checked="checked"{/if}>
			    {'Google+'|translate}
			</label>
		    </li>
		    <li id="social_pinterest" class="ident">
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_pinterest"{if $theme_config->social_pinterest}  checked="checked"{/if}>
			    {'Pinterest'|translate}
			</label>
		    </li>
		    <li id="social_vk" class="ident">
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_vk"{if $theme_config->social_vk}  checked="checked"{/if}>
			    {'VK'|translate}
			</label>
		    </li>
		    <li id="social_buttons">
			<label class="font-checkbox">
			    <span class="fa fa-check-square"></span>
			    <input type="checkbox" name="social_buttons"{if $theme_config->social_buttons} checked="checked"{/if}>
			    {'Use colored share buttons instead of icons'|translate}
			</label>
		    </li>
		</ul>
	    </div>
	</div>
	<p>
	    <input class="btn btn-submit" type="submit" name="submit" value="{'Save Settings'|translate}">
	</p>
    </div>
</form>
{footer_script require="jquery"}
(function(){
var targets = {
'input[name="social_enabled"]': ['#social_twitter', '#social_facebook', '#social_google_plus', '#social_pinterest', '#social_vk', '#social_buttons'],
'input[name="fluid_width"]': ['#fluid_width_col_xxl'],
'input[name="logo_image_enabled"]': ['#logo_image_path'],
};
for (selector in targets) {
for (target of targets[selector]) {
jQuery(target).toggle(jQuery(selector).is(':checked'));

(function(target){
jQuery(selector).on('change', function() {
jQuery(target).toggle($(this).is(':checked'));
});
})(target);
}
};
}());

$(document).ready(function(){
$('ul.tabs li').click(function(){
var tab_id = $(this).attr('data-tab');

$('ul.tabs li').removeClass('current');
$('.tab-content').removeClass('current');

$(this).addClass('current');
$("#"+tab_id).addClass('current');
})
})


var select_bootswatch = $("#bootswatch_theme");
var label_bootswatch = $("#bootswatch_theme_label");
var preview = $("#theme_preview");
var cur_theme = '{$theme_config->bootswatch_theme}';
function getBootswatchThemes() {
$.getJSON("https://bootswatch.com/api/3.json", function (data) {
var themes = data.themes;
select_bootswatch.show();
label_bootswatch.show();

themes.forEach(function(value, index){
$name = value.name;
$lname = $name.toLowerCase();
select_bootswatch.append($("<option />")
.val($lname)
.text($name));

if ($lname === cur_theme) {
$('option[value=' + $lname + ']').attr('selected', 'selected');
}
});
preview.html('<img src="../themes/treflez/components/bootswatch/' + select_bootswatch.val() + '/thumbnail.png" width="50%" style="padding: 10px 0;"/>');
preview.show();

}, "json").fail(function(){
$(".alert").toggleClass("alert-info alert-danger");
$(".alert h4").text("Failed to load available Bootswatch Themes!");
});

}

$(document).ready(function() {
if ($('select[name=bootstrap_theme]').val() === 'bootswatch') {
getBootswatchThemes();
} else {
preview.html('<img src="../themes/treflez/admin/img/' + $('select[name=bootstrap_theme]').val() + '.png" style="padding: 10px 0;"/>');
preview.show();
}

link_target = $('select[name=thumbnail_linkto]').val();
if (!$('input[name=photoswipe]').is(':checked') && link_target !== 'photoswipe') {
$('select[name=thumbnail_linkto]').val('picture');
$('select[name=thumbnail_linkto] option[value=photoswipe]').attr('disabled', 'disabled');
$('select[name=thumbnail_linkto] option[value=photoswipe_mobile_only]').attr('disabled', 'disabled');
}


if ($('select[name=page_header]').val() === 'fancy') {
$('#page_header_image').show();
$('#page_header_navbars').show();
} else {
$('#page_header_image').hide();
$('#page_header_navbars').hide();
$('#page_header_full').hide();
}
});

$('select[name=page_header]').change(function() {
if ($(this).val() == 'fancy') {
$('#page_header_image').show();
$('#page_header_navbars').show();
$('#page_header_full').show();
} else {
$('#page_header_image').hide();
$('#page_header_navbars').hide();
$('#page_header_full').hide();
}
});

$('select[name=bootstrap_theme]').change(function() {
var navbar_main_style = 'navbar-dark',
navbar_main_bg = 'bg-dark',
navbar_contextual_style = 'navbar-dark',
navbar_contextual_bg = 'bg-light',
bs_theme = $('select[name=bootstrap_theme]').val();

switch(bs_theme) {
case 'bootstrap-default':
navbar_contextual_style = 'navbar-light';
break;
case (bs_theme.match(/^material-(amber|lime)/) || {}).input:
navbar_contextual_style = 'navbar-light';
navbar_contextual_bg = 'bg-primary';
break;
case (bs_theme.match(/^material/) || {}).input:
navbar_contextual_bg = 'bg-primary';
break;
case (bs_theme.match(/^bootswatch-(litera|simplex)/) || {}).input:
navbar_main_style = 'navbar-light';
navbar_main_bg = 'bg-light';
navbar_contextual_style = 'navbar-light';
navbar_contextual_bg = 'bg-light';
break;
case (bs_theme.match(/^bootswatch-(cyborg|solar)/) || {}).input:
navbar_main_bg = 'bg-dark';
navbar_contextual_bg = 'bg-dark';
break;
case (bs_theme.match(/^bootswatch/) || {}).input:
navbar_main_bg = 'bg-primary';
navbar_contextual_bg = 'bg-primary';
break;
case 'bootswatch':
getBootswatchThemes();
break;
default:
navbar_main_style = 'navbar-dark';
navbar_main_bg = 'bg-dark';
navbar_contextual_style = 'navbar-dark';
navbar_contextual_bg = 'bg-light';
select_bootswatch.empty()
select_bootswatch.hide();
label_bootswatch.hide();
break;
}

$('input[name=navbar_main_style]').attr('value', navbar_main_style);
$('input[name=navbar_main_bg]').attr('value', navbar_main_bg);
$('input[name=navbar_contextual_style]').attr('value', navbar_contextual_style);
$('input[name=navbar_contextual_bg]').attr('value', navbar_contextual_bg);
});

$('input[name=photoswipe]').change(function() {
curr = $('select[name=thumbnail_linkto]').val();
if (!$(this).is(':checked') && curr !== 'picture') {
$('select[name=thumbnail_linkto]').val('picture');
$('select[name=thumbnail_linkto] option[value=photoswipe]').attr('disabled', 'disabled');
$('select[name=thumbnail_linkto] option[value=photoswipe_mobile_only]').attr('disabled', 'disabled');
} else {
$('select[name=thumbnail_linkto] option[value=photoswipe]').removeAttr('disabled');
$('select[name=thumbnail_linkto] option[value=photoswipe_mobile_only]').removeAttr('disabled');
}
});
{/footer_script}
