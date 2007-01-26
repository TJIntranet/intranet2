[<if isset($is_senior)>]
  <a href="[<$I2_ROOT>]seniors/submit">Click here to [<if isset($has_submitted)>]change[<else>]add[</if>] your college destination info</a><br /><br />
[</if>]

[<$num_submitted>] seniors have submitted college plans.<br />

[<if count($seniors) > 0>]
<table cellspacing="0">
	<tr>
		<th>Name</th>
		<th>College</th>
		<th>Major</th>
	</tr>
	[<foreach from=$seniors item=senior>]
	<tr class="[<cycle values="c1,c2">]">
		<td class="pane"><a href="[<$I2_ROOT>]studentdirectory/info/[<$senior.user->uid>]">[<$senior.user->name>]</a></td>
		<td class="pane">[<$senior.dest>][<if ! $senior.dest_sure>] (unsure)[</if>]</td>
		<td class="pane">[<$senior.major>][<if ! $senior.major_sure>] (unsure)[</if>]</td>
	</tr>
	[</foreach>]
</table>
[</if>]