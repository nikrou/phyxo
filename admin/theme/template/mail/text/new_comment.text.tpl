{extends file="mail/text/__layout.text.tpl"}

{block name="content"}
    {'Author: {author}'|translate:['author' => $comment.author]}
    {if $comment_action === 'delete'}
	{'This author removed the comment with ids {ids}'|translate:['ids' => $comment.IDS]}
    {elseif $comment_action === 'edit'}
	{'This author modified following comment'|translate}
	{$comment.content}
    {else}
	{'Email: {email}'|translate:['email' => $comment.email]}
	{'Comment'|translate}
	{$comment.content}
	{if !empty($comment_url)}
	    {'Manage this user comment'|translate}: {$comment_url}
	{/if}
	{if $comment_action === 'moderate'}
	    {'This comment requires validation'|translate}
	{/if}
    {/if}
{/block}
