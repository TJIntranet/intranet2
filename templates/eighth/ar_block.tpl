[<include file="eighth/header.tpl">]
<table style="border: 0px; margin: 0px; padding: 0px;">
	<tr>
		<th style="text-align: left; padding: 0px 5px;">Date</th>
		<th style="text-align: left; padding: 0px 5px;">Block</th>
		<td>&nbsp;</td>
	</tr>
[<foreach from=$blocks item='block'>]
	<tr>
		<td style="padding: 0px 5px;">[<$block.date|date_format:"%A, %b %e, %Y">]</td>
		<td style="padding: 0px 5px;">[<$block.block>] block</td>
		<td style="padding: 0px 5px;"><a href="[<$I2_ROOT>]eighth/ar_block/remove/bid/[<$block.bid>]">Remove</a>
	</tr>
[</foreach>]
</table>
<br />
<form action="[<$I2_ROOT>]eighth/ar_block/add" method="post">
[<html_select_date prefix="" start_year="-1" end_year="+1" day_format="%d" day_value_format="%02d">]<br />
<table>
	<tr><td><input type="checkbox" name="blocks[]" value="A" checked="checked" /> A block</td><td><input type="checkbox" name="blocks[]" value="B" checked="checked" /> B block</td></tr>
	<tr><td><input type="checkbox" name="blocks[]" value="C" /> C block</td><td><input type="checkbox" name="blocks[]" value="D" /> D block</td></tr>
	<tr><td><input type="checkbox" name="blocks[]" value="E" /> E block</td><td><input type="checkbox" name="blocks[]" value="F" /> F block </td></tr>
</table>
	<input type="submit" value="Add" />
</form>