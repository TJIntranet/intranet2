[<if isset($user)>]
	[<include file="privacy/view.tpl" admin=1 user=$user>]
[<else>]
	[<if isset($info)>]
		[<include file="search/search_results_pane.tpl" return_destination='privacy' results_destination='privacy/'>]
	[<else>]
		[<include file="search/search_pane.tpl" search_destination='privacy'>]
	[</if>]
[</if>]
