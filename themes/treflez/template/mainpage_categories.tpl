{extends file="__layout.tpl"}

{block name="content"}
    {* this might sound ridiculous, but we want to fit the thumbnails to 90% of col-xs-12 without them being too blurry *}
    {assign var=width value=520}
    {assign var=height value=360}
    {define_derivative name='derivative_params' width=$width height=$height crop=true}
    {define_derivative name='derivative_params_square' type=\Phxyo\Image\ImageStdParams::IMG_SQUARE}

    {include file='_albums.tpl'}
{/block}
