<?php

	function create_plan_menu()
	{
?>
<h4>Create a Reading Plan</h4>
<form action="biblefox-plan.php" method="post">
<input type="hidden" name="hidden_field" value="Y">
Which books?<br/>
<textarea rows="5" cols="20" wrap="physical" name="books"></textarea><br/>
How Fast?<br/>
<input type="text" size="10" maxlength="40" name="num_chapters"> chapters per
<select name="frequency">
<option>day</option>
<option>week</option>
<option>month</option>
</select>
<input type="submit" />
</form>
<?php
	}

?>
