[<include file="eighth/eighth_header.tpl">]
<span style="font-family: courier;">
Activity:&nbsp;&nbsp;&nbsp;[<$activity->name>][<if $activity->restricted >] (R)[</if>]<br />
Date:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[<$activity->block->date|date_format>], [<$activity->block->block>] block<br />
Room(s):&nbsp;&nbsp;&nbsp;&nbsp;[<$activity->block_rooms_comma>]<br />
Sponsor(s):&nbsp;[<$activity->block_sponsors_comma>]<br />
<br />
[<php>]
	$this->_tpl_vars['users'] = User::sort_users($this->_tpl_vars['activity']->members);
[</php>]

[<* FIXME: get rid of embedded PHP *>]

Special Info: [<$activity->advertisement>]
<br />


[<foreach from=$users item="user">]
________ [<$user->name_comma>] ([<$user->uid>]) - [<$user->grade>]<br />
[</foreach>]
<br />
[<php>] $this->_tpl_vars['count'] = count($this->_tpl_vars['users']); [</php>]
[<$count>] student[<if $count != 1>]s[</if>] signed up <br />
<div style="float: right; margin: 10px;">
	<a href="[<$I2_ROOT>]eighth/vp_roster/format/aid/[<$activity->aid>]/bid/[<$activity->bid>]"><img src="[<$I2_ROOT>]www/pics/eighth/printer.png" alt="Print" title="Print" /></a>
</div>
