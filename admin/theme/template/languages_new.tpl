{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Languages'|translate}</a></li>
    <li class="breadcrumb-item">{'Add New Language'|translate}</li>
{/block}

{block name="content"}
    {if !empty($languages)}
	<div class="table-responsive">
	    <table class="table table-striped table-hovered" style="width:100%">
		<thead>
		    <tr>
			<td>{'Language'|translate}</td>
			<td>{'Version'|translate}</td>
			<td>{'Date'|translate}</td>
			<td>{'Author'|translate}</td>
			<td>{'Actions'|translate}</td>
		    </tr>
		</thead>
		<tbody>
		    {foreach $languages as $language}
			<tr class="language {if $language@index is odd}odd{else}even{/if}">
			    <td><a href="{$language.EXT_URL}" title="{$language.EXT_NAME}|{$language.EXT_DESC|@htmlspecialchars|@nl2br}">{$language.EXT_NAME}</a></td>
			    <td><a href="{$language.EXT_URL}" title="{$language.EXT_NAME}|{$language.VER_DESC|@htmlspecialchars|@nl2br}">{$language.VERSION}</a></td>
			    <td>{$language.DATE}</td>
			    <td>{$language.AUTHOR}</td>
			    <td>
				<a class="btn btn-sm btn-submit" href="{$language.URL_INSTALL}">{'Install'|translate}</a>
				<a class="btn btn-sm btn-success" href="{$language.URL_DOWNLOAD}">{'Download'|translate}</a>
			    </td>
			</tr>
		    {/foreach}
		</tbody>
	    </table>
	</div>
   {else}
       <p>{'There is no other language available.'|translate}</p>
   {/if}
{/block}
