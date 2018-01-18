{if isset($errors) }
    <div class="errors">
	<ul>
	    {foreach $errors as $error}
		<li>{$error}</li>
	    {/foreach}
	</ul>
    </div>
{/if}

{if not empty($infos)}
    <div class="infos">
	<ul>
	    {foreach $infos as $info}
		<li>{$info}</li>
	    {/foreach}
	</ul>
    </div>
{/if}
