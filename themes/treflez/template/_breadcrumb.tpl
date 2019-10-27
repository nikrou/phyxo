<nav aria-label="nav-breadcrumb d-inline-flex">
    <ol class="breadcrumb">
	<li class="breadcrumb-item"><a href="{$U_HOME}" title="{'Home'|translate}"><i class="fa fa-home" aria-hidden="true"></i></a></li>
	{foreach $elements as $element}
	    {if isset($element.url)}
		<li class="breadcrumb-item"><a href="{$element.url}">{$element.label}</a></li>
	    {elseif isset($element.label)}
		<li class="breadcrumb-item active">{$element.label}</li>
	    {else}
		<li class="breadcrumb-item active">{$element}</li>
	    {/if}
	{/foreach}
    </ol>
</nav>
