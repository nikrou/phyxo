{$MAIL_TITLE}
{if !empty($MAIL_SUBTITLE)}{$MAIL_SUBTITLE}{/if}
----
{block name="content"}{/block}


----
{'Sent by'|translate} "{$GALLERY_TITLE}" {$GALLERY_URL}
{'Powered by'|translate} "Phyxo{if !empty($PHYXO_VERSION)} {$PHYXO_VERSION}{/if}" {$PHYXO_URL}
{'Contact'|translate}: {$CONTACT_MAIL}
