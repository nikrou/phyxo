{extends file="mail/html/__layout.html.tpl"}

{block name="content"}
    <p>
	{'Someone requested that the password be reset for the following user account:'|translate}
	{'Username "{username}" on gallery {url}'|translate:['username' => $user['username'], 'url' => $gallery_url]}
    </p>
    <p>
	{'To reset your password, visit the following address:'|translate}
	{'<a href="{url}">{label}</a>'|translate:['url' => $url, 'confirmation link'|translate]}

    </p>
    <p>{'If this was a mistake, just ignore this email and nothing will happen.'|translate}</p>
{/block}
