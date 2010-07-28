<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];
$adminPath = ADMINPANEL_WEB_PATH;
$moduleName = htmlentities($_REQUEST['modulename']);

if (isset($_REQUEST['topdealid']) && !empty($_REQUEST['topdealid']))
{
	// This is an edit request
	$formTitle = "Edit Top Deal";
	$id = (int)$_REQUEST['topdealid'];
	$query = "SELECT `enabled`, `link`, `image`, `image_alttext`, `impression_image`, `linktext`, `subtext`, `start_date`, `end_date`, `delete_if_expired`, `ordering` FROM " . DEALHUNTING_TOPDEALS_TABLE . " WHERE id=$id LIMIT 1;";
	$dealhuntingDatabase->query($query,false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
		exit();
	}

	if ($dealhuntingDatabase->rowCount() == 0)
	{
		returnError(201, 'The requested ID was not found in the Top Deals database table.', false, null, 'ajax');
		exit();
	}

	$row = $dealhuntingDatabase->firstObject();

	if ($row->enabled == '1')
	{
		$adEnabled = true;
	}
	else
	{
		$adEnabled = false;
	}
	$link						= stripslashes($row->link);
	$image						= stripslashes($row->image);
	$altText					= stripslashes($row->image_alttext);
	$impression					= stripslashes($row->impression_image);
	$linkText					= stripslashes($row->linktext);
	$additionalText				= stripslashes($row->subtext);
	$startDate					= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->start_date));
	$endDate					= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->end_date));
	$orderby					= (int)$row->ordering;
	
	if ($row->delete_if_expired == '1')
	{
		$deleteIfExpired = true;
	}
	else
	{
		$deleteIfExpired = false;
	}

	$task							= 'saveChanges';
}
else
{
	// This is a new Top Deal
	$formTitle					= "Create Top Deal";			// Text
	$id							= null;							// Integer
	$adEnabled					= true;							// Boolean
	$link						= 'http://';					// URL
	$image						= 'http://';					// URL
	$altText					= '';							// Text
	$impression					= '';							// URL
	$linkText					= '';							// Text
	$additionalText				= '';							// Text
	$startDate					= '';							// MySQL Date
	$endDate					= '';							// MySQL Date
	$orderby					= 1;							// Integer
	$deleteIfExpired			= false;						// Boolean
	$task						= 'saveNew';					// Text
}

if ($adEnabled)
{
	$adEnabledYesString = 'checked="checked"';
	$adEnabledNoString = '';
}
else
{
	$adEnabledYesString = '';
	$adEnabledNoString ='checked="checked"';
}

if ($deleteIfExpired)
{
	$deleteIfExpiredYesString = 'checked="checked"';
	$deleteIfExpiredNoString = '';
}
else
{
	$deleteIfExpiredYesString = '';
	$deleteIfExpiredNoString = 'checked="checked"';
}


echo <<< HEREDOC1
<span class="edit_div_title">$formTitle</span>
	<form action="$scriptName" method="post">
	<table>
		<tr>
			<td valign="top" id="editDivImage_td">
				<img id="editDivImage" width="95" src="$image" />
			</td>
			<td class="detail_cell">
				<label>Enabled</label>
				<input type="radio" name="ad_enabled" id="edit_ad_enabled_yes" class="radiobutton" style="width: 13px;" value="1"$adEnabledYesString />Yes
				<input type="radio" name="ad_enabled" id="edit_ad_enabled_no" class="radiobutton" style="width: 13px;" value="0"$adEnabledNoString />No<br />
				<label>Link</label>
				<input type="text" name="link" id="edit_link" value="$link" /><br />
				<label>Image</label>
				<input type="text" name="image" id="edit_image" onblur="updateEditImage(this);" value="$image" /><br />
				<label>Hit Tracker</label>
				<input type="text" name="impression" id="edit_impression" value="$impression" /><br />
				<label>ALT Text</label>
				<input type="text" name="alttext" id="edit_alttext" value="$altText" /><br />
				<label>Link Text</label>
				<input type="text" name="linktext" id="edit_linktext" value="$linkText" /><br />
				<label>Addt'l Text</label>
				<input type="text" name="subtext" id="edit_subtext" value="$additionalText" /><br />
				<label>Start Date</label>
				<input type="text" name="edit_start_date" class="dhdatepicker" id="edit_start_date" value="$startDate" /><br />
				<label>End Date</label>
				<input type="text" name="edit_end_date" class="dhdatepicker" id="edit_end_date" value="$endDate" /><br />
				<label>Delete When Expired</label>
				<input type="radio" name="delete_if_expired" id="edit_delete_if_expired_yes" class="radiobutton" style="width: 13px;" value="1"$deleteIfExpiredYesString />Yes
				<input type="radio" name="delete_if_expired" id="edit_delete_if_expired_no" class="radiobutton" style="width: 13px;" value="0"$deleteIfExpiredNoString />No
			</td>
		</tr>
	</table>
				<div class="form_button_div">
					<input type="submit" name="submit" onclick="return validateForm_topdeals('$adminPath', '$moduleName');" value="Submit" />
					<input type="button" name="cancel" onclick="hideEditDiv();" value="Cancel" />
				</div>
				<input type="hidden" name="topdealid" id="topdealid" value="$id" />
				<input type="hidden" name="orderby" value="$orderby" />
				<input type="hidden" name="module" id="edit_module" value="$module" />
				<input type="hidden" name="task" id="edit_task" value="$task" />
	<form>
</div>
HEREDOC1;
?>