{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_ALBUMS}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album'|translate}: {$CATEGORIES_NAV}</li>
    <li class="breadcrumb-item">{'Notification'|translate}</li>
{/block}

{block name="content"}
    <form action="{$F_ACTION}" method="post" id="categoryNotify">

	<fieldset id="emailCatInfo">
	    <legend>{'Send an information email to group members'|translate}</legend>

	    {if isset($group_mail_options)}

		<p>
		    <strong>{'Group'|translate}</strong>
		    <br>
		    <select name="group">
			{html_options options=$group_mail_options}
		    </select>
		</p>

		<p>
		    <strong>{'Complementary mail content'|translate}</strong>
		    <br>
		    <textarea cols="50" rows="5" name="mail_content" id="mail_content" class="description">{$MAIL_CONTENT}</textarea>
		</p>

		<p>
		    <input class="submit" type="submit" value="{'Send'|translate}" name="submitEmail">
		</p>

	    {elseif isset($no_group_in_gallery) and $no_group_in_gallery}
		<p>{'There is no group in this gallery.'|translate} <a href="admin/index.php?page=group_list" class="externalLink">{'Group management'|translate}</a></p>
	    {else}
		<p>
		    {'No group is permitted to see this private album'|translate}.
		    <a href="{$permission_url}" class="externalLink">{'Permission management'|translate}</a>
		</p>
	    {/if}
	</fieldset>

    </form>
{/block}
