{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'Albums'|translate}</a></li>
    <li class="breadcrumb-item">{'Album'|translate}:
	{foreach $CATEGORIES_NAV as $category_nav}
	    <a href="{$category_nav.url}">{$category_nav.name}</a>
	    {if !$category_nav@last}/{/if}
	{/foreach}
    </li>
    <li class="breadcrumb-item">{'Notification'|translate}</li>
{/block}

{block name="content"}
    <form action="{$F_ACTION}" method="post" id="categoryNotify">

	<div class="fieldset">
	    <h3>{'Send an information email to group members'|translate}</h3>

	    {if isset($group_mail_options)}
		<p>
		    <label>{'Group'|translate}</label>
		    <select class="custom-select" name="group">
			{html_options options=$group_mail_options}
		    </select>
		</p>

		<p>
		    <label>{'Complementary mail content'|translate}</label>
		    <textarea cols="50" rows="5" name="mail_content" id="mail_content" class="form-control">{$MAIL_CONTENT}</textarea>
		</p>

		<p>
		    <input class="btn btn-submit" type="submit" value="{'Send'|translate}" name="submitEmail">
		</p>

	    {elseif isset($no_group_in_gallery) and $no_group_in_gallery}
		<p>{'There is no group in this gallery.'|translate} <a href="{$U_GROUPS}">{'Group management'|translate}</a></p>
	    {else}
		<p>
		    {'No group is permitted to see this private album'|translate}.
		    <a href="{$permission_url}" class="externalLink">{'Permission management'|translate}</a>
		</p>
	    {/if}
	</div>

    </form>
{/block}
