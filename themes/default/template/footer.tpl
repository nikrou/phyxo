<div id="copyright">
  {if isset($debug.TIME)}
    {'Page generated in'|translate} {$debug.TIME} ({$debug.NB_QUERIES} {'SQL queries in'|translate} {$debug.SQL_TIME}) -
  {/if}

  {'Powered by'|translate} <a href="{$PHPWG_URL}" class="Piwigo">Phyxo</a>
  {$VERSION}
  {if isset($CONTACT_MAIL)}
  - <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|@escape:url}">{'Contact webmaster'|translate}</a>
  {/if}

  {if isset($footer_elements)}
  {foreach from=$footer_elements item=elt}
    {$elt}
  {/foreach}
  {/if}
</div>{* <!-- copyright --> *}

{if isset($debug.QUERIES_LIST)}
<div id="debug">
  {foreach from=$debug.QUERIES_LIST item=query}
  {$query.sql}
  {/foreach}
</div>
{/if}
</div>{* <!-- the_page --> *}

<!-- BEGIN get_combined -->
{get_combined_scripts load='footer'}
<!-- END get_combined -->

</body>
</html>
