[<if isset($error)>]
[<$error>]
[<else>]

<h2>
    [<if isset($sso['title'])>]
        The application [<$sso['title']|escape>]
    [<else>]
        An unnamed application
    [</if>]
    [<if isset($sso['author'])>]
        by [<$sso['author']|escape>] 
    [</if>]
    would like to access your Intranet account.
</h2>
<h3>
    If you <b>fully trust</b> this application[<if isset($sso['author'])>] and developer[</if>], press the OK button below.
</h3>
You will be redirected to <a href="[<$sso['return']>]">[<$sso['return']>]</a>. The token sent will expire in [<if $exphrs lt 1>]less than one hour[<else>][<$exphrs|escape>] hours[</if>].
<br />
<br />
[<$redir>] &nbsp; &nbsp; <button onclick="history.back()">Cancel</button>


[</if>]
