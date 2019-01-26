{combine_css path="themes/treflez/admin/css/admin.css"}
{combine_script id='treflez-admin' load='footer' path='themes/treflez/admin/js/admin.js'}

<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link active" href="#appearance">{'Appearance'|translate}</a></li>
    <li class="nav-item"><a class="nav-link" href="#components">{'Components'|translate}</a></li>
    <li class="nav-item"><a class="nav-link" href="#social-integration">{'Social Media Integration'|translate}</a></li>
    <li class="nav-item"><a class="nav-link" href="#about">{'About'|translate}</a></li>
</ul>

<form method="post" class="properties">
    <div id="appearance" class="tab-content active">
	<div  class="fieldset">
	    <h3>{'Full width layout'|translate}</h3>

	    <p class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="fluid_width" name="fluid_width"{if $theme_config->fluid_width} checked="checked"{/if}>
		<label class="custom-control-label" for="fluid_width">{'Enabled'|translate}</label>
		<span class="form-text text-muted">{'Use full width containers that span the entire width of the viewport'|translate}</span>
	    </p>
	    <p class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="fluid_width_col_xxl" name="fluid_width_col_xxl"{if $theme_config->fluid_width_col_xxl} checked="checked"{/if}>
		<label class="custom-control-label" for="fluid_width_col_xxl">{'Use 6 colums for viewports >= 1680px'|translate}</label>
	    </p>
	</div>
        <div class="fieldset">
	    <h3>{'Site logo'|translate}</h3>

	    <p class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="logo_image_enabled" name="logo_image_enabled"{if $theme_config->logo_image_enabled} checked="checked"{/if}>
		<label class="custom-control-label" for="logo_image_enabled">{'Enabled'|translate}</label>
		<span class="form-text text-muted">{'Display a site logo image instead of plain text'|translate}</span>
	    </p>
	    <p>
		<label>
                    {'Path'|translate}
                    <input type="text" name="logo_image_path" size="50" {if $theme_config->logo_image_path != ""}value="{$theme_config->logo_image_path}"{else}placeholder="{'URL or releative path to the image'|translate}"{/if}>
		</label>
		<span class="form-text text-muted">{'The path to the image, relative to your Phyxo installation folder'|translate}</span>
	    </p>
	</div>
        <div class="fieldset">
	    <h3>{'Page header'|translate}</h3>
	    <p>
		<label for="page_header">{'Banner style'|translate}</label>
		<select class="custom-select" name="page_header">
                    <option value="jumbotron"{if $theme_config->page_header == 'jumbotron'} selected="selected"{/if}>{'Jumbotron'|translate}</option>
                    <option value="fancy"{if $theme_config->page_header == 'fancy'} selected="selected"{/if}>{'Hero image'|translate}</option>
                    <option value="none"{if $theme_config->page_header == 'none'} selected="selected"{/if}>{'Disabled'|translate}</option>
		</select>
	    </p>
	    <p class="fancy{if $theme_config->page_header != 'fancy'} d-none{/if}">
		<label>
                    {'Background image'|translate}
                    <input type="text" name="page_header_image" size="50" {if $theme_config->page_header_image != ""}value="{$theme_config->page_header_image}"{else}placeholder="{'URL or releative path to the image'|translate}"{/if}>
		</label>
		<span class="form-text text-muted">{'URL or releative path to the image'|translate}</span>
	    </p>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="page_header_both_navs" name="page_header_both_navs"{if $theme_config->page_header_both_navs} checked=checked{/if}>
		<label class="custom-control-label" for="page_header_both_navs">{'Integrate lower navbar'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox fancy{if $theme_config->page_header != 'fancy'} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="page_header_full" name="page_header_full"{if $theme_config->page_header_full} checked=checked{/if}>
		<label class="custom-control-label" for="page_header_full">{'Span the full viewport height'|translate}</label>
	    </p>
        </div>
        <div class="fieldset">
	    <h3>{'Category page display'|translate}</h3>
	    <p>
		<label for="category_wells">{'Display categories as Bootstrap media wells'|translate}</label>
		<select class="custom-select" name="category_wells" id="category_wells">
                    <option value="never"{if $theme_config->category_wells == 'never'} selected="selected"{/if}>{'Never'|translate}</option>
                    <option value="always"{if $theme_config->category_wells == 'always'} selected="selected"{/if}>{'Always'|translate}</option>
                    <option value="mobile_only"{if $theme_config->category_wells == 'mobile_only'} selected="selected"{/if}>{'On mobile devices only'|translate}</option>
		</select>
		<span class="form-text text-muted">{'This will display categories as media wells with squared thumbnails, similar to the smartpocket mobile theme.'|translate}</span>
	    </p>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="cat_descriptions" name="cat_descriptions"{if $theme_config->cat_descriptions} checked=checked{/if}>
		<label class="custom-control-label" for="cat_descriptions">{'Display category description in grid view'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="cat_nb_images" name="cat_nb_images"{if $theme_config->cat_nb_images} checked=checked{/if}>
		<label class="custom-control-label" for="cat_nb_images">{'Display number of images in album and subalbums'|translate}</label>
	    </p>
        </div>
        <div class="fieldset">
	    <h3>{'Thumbnail page display'|translate}</h3>
	    <p class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="thumbnail_caption" name="thumbnail_caption"{if $theme_config->thumbnail_caption} checked="checked"{/if}>
		<label class="custom-control-label" for="thumbnail_caption">{'Show image caption'|translate}</label>
	    </p>
	    <p>
       		<label for="thumbnail_linkto">{'Link thumbnail to'|translate}</label>
		<select class="custom-select" name="thumbnail_linkto">
                    <option value="picture"{if $theme_config->thumbnail_linkto == 'picture'} selected="selected"{/if}>{'Picture details page'|translate}</option>
                    <option value="photoswipe"{if $theme_config->thumbnail_linkto == 'photoswipe'} selected="selected"{/if}>{'PhotoSwipe Slideshow'|translate}</option>
                    <option value="photoswipe_mobile_only"{if $theme_config->thumbnail_linkto == 'photoswipe_mobile_only'} selected="selected"{/if}>{'Photoswipe Slideshow (Mobile devices only)'|translate}</option>
		</select>
	    </p>

	    <h4>{'Description display style'|translate}</h4>
	    <p class="custom-control custom-radio">
		<input type="radio" class="custom-control-input" id="thumbnail_cat_desc_simple" name="thumbnail_cat_desc" value="simple"{if $theme_config->thumbnail_cat_desc == 'simple'} checked="checked"{/if} />
                <label class="custom-control-label" for="thumbnail_cat_desc_simple">{'Simple'|translate}</label>
                <span class="form-text text-muted">{'center-aligned h5 heading'|translate}</span>
	    </p>
	    <p class="custom-control custom-radio">
		<input type="radio" class="custom-control-input" name="thumbnail_cat_desc" id="thumbnail_cat_desc_advanced" value="advanced"{if $theme_config->thumbnail_cat_desc == 'advanced'} checked="checked"{/if} />
                <label class="custom-control-label" for="thumbnail_cat_desc_advanced">{'Advanced'|translate}</label>
                <span class="form-text text-muted">{'left-aligned free text for advanced descriptions'|translate}</span>
	    </p>
	</div>
        <div class="fieldset">
	    <h3>{'Picture page display'|translate}</h3>
	    <p>
		<label for="picture_info">{'Picture info display position'|translate}</label>
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
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="slick_enabled" name="slick_enabled"{if $theme_config->slick_enabled} checked="checked"{/if}>
		<label class="custom-control-label" for="slick_enabled">{'Enabled'|translate}</label>
		<span class="form-text text-muted">{'Enable the slick carousel below the main image on the picture page'|translate}.</span>
	    </p>
	    <p>
		<label for="slick_lazyload">{'lazyLoad method'|translate}</label>
		<select class="custom-select" name="slick_lazyload">
		    <option value="ondemand"{if $theme_config->slick_lazyload == 'ondemand'} selected="selected"{/if}>ondemand</option>
		    <option value="progressive"{if $theme_config->slick_lazyload == 'progressive'} selected="selected"{/if}>progressive</option>
		</select>
		<span class="form-text text-muted">
		    <em>ondemand</em> {'will load the image as soon as you slide to it'|translate}.
		    <em>progressive</em> {'loads all images one after another when the page loads (use carefully!)'|translate}.
		</span>
	    </p>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="slick_infinite" name="slick_infinite"{if $theme_config->slick_infinite} checked="checked"{/if}>
		<label class="custom-control-label" for="slick_infinite">{'Infinite looping'|translate}</label>
		<span class="form-text text-muted">{'Endlessly scroll through album images'|translate}</span>
	    </p>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="slick_centered" name="slick_centered"{if $theme_config->slick_centered} checked="checked"{/if}>
		<label class="custom-control-label" for="slick_centered">{'Center mode'|translate}</label>
		<span class="form-text text-muted">{'Display the currently selected image in the middle. Works best with infinite looping enabled.'|translate}</span>
	    </p>
	</div>

	<div class="fieldset">
	    <h3>PhotoSwipe {'Settings'|translate}</h3>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="photoswipe" name="photoswipe"{if $theme_config->photoswipe} checked="checked"{/if}>
		<label class="custom-control-label" for="photoswipe">{'Enabled'|translate}</label>
		<span class="form-text text-muted">{'Enable PhotoSwipe fullscreen slideshow. Disable if you prefer to use Plugins like Fotorama or Phyxo\'s default slideshow.'|translate}</span>
	    </p>
	    <p>
		<label for="photoswipe_interval">{'Autoplay interval'|translate}</label>
		<input type="number" name="photoswipe_interval" value="{$theme_config->photoswipe_interval}" min="1000" max="50000"> {'milliseconds'|translate}
	    </p>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="photoswipe_metadata" name="photoswipe_metadata"{if $theme_config->photoswipe_metadata} checked="checked"{/if}>
		<label class="custom-control-label" for="photoswipe_metadata">{'Show basic EXIF metadata'|translate}</label>
		<span class="form-text text-muted">{'For more information on metadata visit'|translate} <a href="https://github.com/tkuther/piwigo-bootstrap-darkroom/wiki/EXIF-Metadata-in-PhotoSwipe">Wiki:EXIF-Metadata-in-PhotoSwipe</a></span>
	    </p>
	</div>
	<div class="fieldset">
	    <h3>{'Quick search'|translate}</h3>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="quicksearch_navbar" name="quicksearch_navbar"{if $theme_config->quicksearch_navbar} checked="checked"{/if}>
		<label class="custom-control-label" for="quicksearch_navbar">{'Quick search'|translate} {'directly in the navigation bar'|translate}</label>
	    </p>
	</div>

	<div class="fieldset">
	    <h3>{'Tag cloud'|translate}</h3>
	    <p class="custom-control custom-radio">
		<input type="radio" class="custom-control-input" id="tag_cloud_type_basic" name="tag_cloud_type" value="basic"{if $theme_config->tag_cloud_type == 'basic'} checked="checked"{/if} />
		<label class="custom-control-label" for="tag_cloud_type_basic">{'Basic'|translate}</label>
	    </p>
	    <p class="custom-control custom-radio">
		<input type="radio" class="custom-control-input" id="tag_cloud_type_html5" name="tag_cloud_type" value="html5"{if $theme_config->tag_cloud_type == 'html5'} checked="checked"{/if} />
		<label class="custom-control-label" for="tag_cloud_type_html5">{'HTML 5 canvas'|translate}</label>
	    </p>
	</div>
    </div>

    <div id="social-integration" class="tab-content">
	<div class="fieldset">
	    <h3>{'Social integration'|translate}</h3>
	    <p class="custom-control custom-checkbox">
		<input type="checkbox" class="custom-control-input" id="social_enabled" name="social_enabled"{if $theme_config->social_enabled} checked="checked"{/if}>
		<label class="custom-control-label" for="social_enabled">{'Enabled'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox social{if !$theme_config->social_enabled} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="social_twitter" name="social_twitter"{if $theme_config->social_twitter}  checked="checked"{/if}>
		<label class="custom-control-label" for="social_twitter">{'Twitter'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox social{if !$theme_config->social_enabled} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="social_facebook" name="social_facebook"{if $theme_config->social_facebook}  checked="checked"{/if}>
		<label class="custom-control-label" for="social_facebook">{'Facebook'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox social{if !$theme_config->social_enabled} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="social_google_plus" name="social_google_plus"{if $theme_config->social_google_plus}  checked="checked"{/if}>
		<label class="custom-control-label" for="social_google_plus">{'Google+'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox social{if !$theme_config->social_enabled} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="social_pinterest" name="social_pinterest"{if $theme_config->social_pinterest}  checked="checked"{/if}>
		<label class="custom-control-label" for="social_pinterest">{'Pinterest'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox social{if !$theme_config->social_enabled} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="social_vk" name="social_vk"{if $theme_config->social_vk}  checked="checked"{/if}>
		<label class="custom-control-label" for="social_vk">{'VK'|translate}</label>
	    </p>
	    <p class="custom-control custom-checkbox social{if !$theme_config->social_enabled} d-none{/if}">
		<input type="checkbox" class="custom-control-input" id="social_buttons" name="social_buttons"{if $theme_config->social_buttons} checked="checked"{/if}>
		<label class="custom-control-label" for="social_buttons">{'Use colored share buttons instead of icons'|translate}</label>
	    </p>
	</div>
    </div>

    <div id="about" class="tab-content">
	<div class="fieldset">
	    <h3>{'Treflez'|translate}</h3>
	    <h4>{'Version'|translate}: 0.1.0</h4>
	    <h5>{'By'|translate}: <a href="https://github.com/nikrou/phyxo">Nicolas Roudaire</a></h5>

	    <p>{'A mobile-ready theme based on Bootstrap'|translate}</p>
	    <p><a href="https://github.com/nikrou/phyxo/issues">{'Bug reports and feature requests'|translate}</a></p>
	</div>
    </div>

    <p>
	<input type="hidden" name="_settings" value="true" />
	<input class="btn btn-submit" type="submit" name="submit" value="{'Save Settings'|translate}">
    </p>
</form>
