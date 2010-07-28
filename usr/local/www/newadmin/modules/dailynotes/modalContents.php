<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

if (isset($_REQUEST['noteid']) && !empty($_REQUEST['noteid']))
{
	// This is an edit request. Gather the information about this daily note from the database
	$formTitle	= "Edit Daily Note";
	$task		= "saveEdit";
	
	$id			= (int)$_REQUEST['noteid'];
	
	$dealhuntingDatabase->query("SELECT `top`, `note`, `showdate` FROM " . DEALHUNTING_DAILYNOTES_TABLE . " WHERE `id`=$id LIMIT 1;");
	
	$row		= $dealhuntingDatabase->firstObject(); 
	
	$top		= (int)$row->top;
	$note		= htmlentities(stripslashes($row->note));
	$showdate	= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->showdate));
}
else
{
	// This is a new daily note
	$formTitle	= "Create Daily Note";
	$task		= "saveNew";
	
	$id			= null;
	$note		= "";
	$top		= 1;
	$showdate	= "";
	
}

$topSelectOptions = "\n\t<option value=\"1\" ";
if ($top == 1)
{
	$topSelectOptions .= "selected=\"selected\"";
}
$topSelectOptions .= ">Yes</option><option value=\"0\" ";
if (!$top)
{
	$topSelectOptions .= "selected=\"selected\"";
}
$topSelectOptions .= ">No</option>\n";

echo <<< HEREDOC

<span class="edit_div_title">$formTitle</span>
	<table>
		<tr>
			<td class="detail_cell">
				<form action="$scriptName" method="post">
					<p>
						<label for="edit_note">Note:</label>
						<textarea rows="4" name="edit_note" id="edit_note">$note</textarea><br />
						<label for="edit_showdate">Show Date</label>
						<input type="text" name="edit_showdate" class="dhdatepicker" id="edit_showdate" value="$showdate" /><br />
						<label for="edit_top">Top?</label>
						<select name="edit_top" id="edit_top">
							$topSelectOptions
						</select>
					</p>
					<div class="form_button_div">
						<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_dailynotes('{$scriptName}', {$module});" />
						<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
					</div>
					<input type="hidden" name="noteid" id="edit_noteid" value="$id" />
					<input type="hidden" name="task" id="edit_task" value="$task" />
					<input type="hidden" name="module" value="$module" />
				</form>
			</td>
		</tr>
	</table>
HEREDOC;
?>