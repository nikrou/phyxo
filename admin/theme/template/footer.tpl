</div>{* <!-- pwgMain --> *}

{if isset($footer_elements)}
{foreach from=$footer_elements item=elt}
  {$elt}
{/foreach}
{/if}

{if isset($debug.QUERIES_LIST)}
<div id="debug">
  {foreach from=$debug.QUERIES_LIST item=query}
  {$query.sql}
  {/foreach}
</div>
{/if}

<div id="footer">
  <div id="piwigoInfos">
    {* Please, do not remove this copyright. If you really want to,
    contact us on http://www.phyxo.net/ to find a solution on how
    to show the origin of the script...
    *}

    {'Powered by'|translate}
    <a class="externalLink" href="{$PHPWG_URL}" title="{'Visit Phyxo project website'|translate}">Phyxo</a>
    {$VERSION}
  </div>

  <div id="pageInfos">
    {if isset($debug.TIME) }
    {'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
    {/if}

    {'Contact'|translate}
    <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
  </div>{* <!-- pageInfos --> *}

</div>{* <!-- footer --> *}
</div>{* <!-- the_page --> *}

{combine_script id='jquery.tipTip' load='footer' path='admin/theme/js/plugins/jquery.tipTip.js'}
{footer_script require='jquery.tipTip'}
jQuery('.tiptip').tipTip({
delay: 0,
fadeIn: 200,
fadeOut: 200
});

jQuery('a.externalLink').click(function() {
window.open(jQuery(this).attr("href"));
return false;
});
{/footer_script}

<!-- BEGIN get_combined -->
{get_combined_scripts load='footer'}
<!-- END get_combined -->

<script src="{asset manifest='theme/build/manifest.json' src='vendor.js'}"></script>
<script src="{asset manifest='theme/treflez/build/manifest.json' src='app.js'}"></script>

</body>
</html>
