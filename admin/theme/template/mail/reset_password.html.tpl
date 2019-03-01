{extends file="mail/__layout.html.tpl"}

{block name="content"}
    <p>
	{'Someone requested that the password be reset for the following user account:'|translate}
	{'Username "%s" on gallery %s'|translate:$user['username']:$gallery_url}
    </p>
    <p>
	{'To reset your password, visit the following address:'|translate}
	{'<a href="%s">%s</a>'|translate:$url:('confirmation link'|translate)}

    </p>
    <p>{'If this was a mistake, just ignore this email and nothing will happen.'|translate}</p>
{/block}
