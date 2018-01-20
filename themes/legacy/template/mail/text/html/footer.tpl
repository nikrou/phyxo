            {* <!-- end $CONTENT --> *}
          </td></tr>

          <tr><td id="footer">
              {* <!-- begin FOOTER --> *}
              {'Sent by'|translate} <a href="{$GALLERY_URL}">{$GALLERY_TITLE}</a>
	      {'Powered by'|translate} <a href="{$PHPWG_URL}">Phyxo</a>
            {if not empty($VERSION)}{$VERSION}{/if}

            - {'Contact'|translate}
            <a href="mailto:{$CONTACT_MAIL}?subject={'A comment on your site'|translate|escape:url}">{'Webmaster'|translate}</a>
            {* <!-- end FOOTER --> *}
          </td></tr>
        </table>

      </td></tr>
    </table>
  </body>
</html>
