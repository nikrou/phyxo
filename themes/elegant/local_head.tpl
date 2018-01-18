{footer_script}
var p_main_menu = "{$elegant.p_main_menu}", p_pict_descr = "{$elegant.p_pict_descr}", p_pict_comment = "{$elegant.p_pict_comment}";
{/footer_script}
{if $BODY_ID=='thePicturePage'}
    {combine_script id="elegant.scripts_picture_page" load="footer" require="jquery" path="themes/elegant/js/scripts_picture_page.js"}
{else}
    {combine_script id="elegant.scripts" load="footer" require="jquery" path="themes/elegant/js/scripts.js"}
{/if}
