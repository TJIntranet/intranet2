[<if !isset($act)>][<include file="eighth/header.tpl">][</if>]
<select name="activity_list" size="10" onChange="location.href='[<$I2_ROOT>]eighth/sch_activity/view/aid/' + this.options[this.selectedIndex].value">
[<foreach from=$activities item="activity">]
	<option value="[<$activity->aid>]"[<if $activity->cancelled>] style="font-weight: bold; color: #FF0000;"[<elseif $activity->scheduled>] style="font-weight: bold; color: #FF6600;"[</if>][<if isset($act) && ($act->aid == $activity->aid)>] SELECTED[</if>]>[<$activity->aid>]: [<$activity->name_r>]</option>
[</foreach>]
</select>
