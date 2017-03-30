{$CONTENT}

{if isset($TECHNICAL)}
    <p>
	{'Connected user: %s'|translate:$TECHNICAL.username}<br>
	{'IP: %s'|translate:$TECHNICAL.ip}<br>
	{'Browser: %s'|translate:$TECHNICAL.user_agent}
    </p>
{/if}
