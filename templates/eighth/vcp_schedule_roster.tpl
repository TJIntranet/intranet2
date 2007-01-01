Roster for <b>[<$activity->name|escape:"html">]</b><br />
Room: [<$activity->block_rooms_comma>]<br />
Date: <b>[<$activity->block->date|date_format:"%A, %B %e %Y">]</b> Block <b>[<$activity->block->block>]</b><br />

[<if $activity->comment|escape:"html">]
<br />
<em>[<$activity->comment>]</em>
<br />
[</if>]

<br />
[<if $activity->member_count > 0>]
<table cellspacing="0" cellpadding="0">
	<tr>
		<th>Name</th>
		<th>Grade</th>
[<* Insert more personal information *>]
	</tr>
[<foreach from=$activity->members_obj item="member">]
	<tr class="[<cycle values="c1,c2">]">
		<td style="padding: 0px 5px;"><a href="[<$I2_ROOT>]studentdirectory/info/[<$member->uid>]">[<$member->name_comma>]</a></td>
		<td style="padding: 0px 5px;">[<$member->grade>]</td>
	</tr>
[</foreach>]
</table><br />
Total: [<$activity->member_count>] (NOTE: this number includes any students that may have elected not to appear on this list)
[<else>]
<b>No students signed up for this block</b>
[</if>]
