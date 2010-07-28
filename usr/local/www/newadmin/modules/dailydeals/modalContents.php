<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

// These are used when creating the Datepicker control
$lowYearRange = date('Y');
$highYearRange = date('Y')+2;

if (isset($_REQUEST['dealid']) && !empty($_REQUEST['dealid']))
{
	// This is an edit request. Gather the information about this daily deal from the database.
	$formTitle	= "Edit Daily Deal";
	$task		= "saveEdit";
	
	$id			= (int)$_REQUEST['dealid'];
	
	$query		= "SELECT"
		. " `store`, `showdate`, `posted`, `whoposted`, `updated`,"
		. " `whoupdated`, `verbose`, `brief`, `subject`, `valid`,"
		. " `invalidreason`, `img`, `imgurl`, `dealurl`, `clicks`, `expire`"
		. " FROM " . DEALHUNTING_DAILYDEALS_TABLE
		. " WHERE `id`=$id LIMIT 1;";
	
	$dealhuntingDatabase->query($query, false);
	
	if ($dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
		exit();
	}
	
	$row = $dealhuntingDatabase->firstObject();
	
	$store			= (int)$row->store;
	$showdate		= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->showdate));
	$posted			= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->posted));
	$whoposted		= htmlentities(stripslashes($row->whoposted));
	$updated		= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->updated));
	$whoupdated		= htmlentities(stripslashes($row->whoupdated));
	$brief			= htmlentities(stripslashes($row->brief));
	$subject		= htmlentities(stripslashes($row->subject));
	$valid			= (int)$row->valid;
	$invalidreason	= htmlentities(stripslashes($row->invalidreason));
	$img			= htmlentities(stripslashes($row->img));
	$imgurl			= htmlentities(stripslashes($row->imgurl));
	$dealurl		= htmlentities(stripslashes($row->dealurl));
	$clicks			= (int)$row->clicks;
	$expire			= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->expire));
}
else
{
	// This is a new daily deal
	$formTitle	= "Create Daily Deal";
	$task		= "saveNew";
	
	$id			= null;
	
	$store			= null;
	$showdate		= date(ADMINPANEL_DATE_FIELD_FORMAT); // Default to 'today'
	$posted			= date(ADMINPANEL_DATE_FIELD_FORMAT);
	$whoposted		= $_SESSION['username'];
	$updated		= '';
	$whoupdated		= '';
	$brief			= '';
	$subject		= '';
	$valid			= 1;
	$invalidreason	= '';
	$img			= '';
	$imgurl			= '';
	$dealurl		= '';
	$clicks			= 0;
	$expire			= null;
}

// Create $selectCompanyOptions
$dealhuntingDatabase->query("SELECT `id`, `company` FROM " . DEALHUNTING_COMPANIES_TABLE . " ORDER BY `company` ASC;", false);
if ($dealhuntingDatabase->error)
{
	returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
}
$selectCompanyOptions = "\n\t<option value=\"\">-- Select One --</option>";
if ($dealhuntingDatabase->rowCount() > 0)
{
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$selectCompanyOptions .= "\n\t<option value=\"" . $row->id . "\"";
		if ($store != null && $store == $row->id)
		{
			$selectCompanyOptions .= " selected=\"selected\"";
		}
		
		$selectCompanyOptions .= ">". $row->company . "</option>"; 
	}
}
else
{
	$selectCompanyOptions = "<option value=\"\">No companies were found</option>";
}

// Create $selectValidOptions
$selectValidOptions = "\n\t<option value=\"1\"";
if ($valid == 1)
{
	$selectValidOptions .= " selected=\"selected\"";
}
$selectValidOptions .= ">Yes</option>\n\t<option value=\"0\"";
if (!$valid)
{
	$selectValidOptions .= " selected=\"selected\"";
}
$selectValidOptions .= ">No</option>";


echo <<< HEREDOC
<span class="edit_div_title">$formTitle</span>
	<table>
		<tr>
			<td class="detail_cell">
				<form action="$scriptName" method="post">
					<p>
						<label for="edit_company">Company</label>
						<select name="edit_company" id="edit_company">
							$selectCompanyOptions
						</select><br />
						<label for="edit_dealurl">Deal URL</label>
						<input type="text" name="edit_dealurl" id="edit_dealurl" value="$dealurl" maxlength="500" />
						<label for="edit_image">Image</label>
						<input type="text" name="edit_image" id="edit_image" value="$img" maxlength="255" />
						<label for="edit_imageurl">Image URL</label>
						<input type="text" name="edit_imageurl" id="edit_imageurl" value="$imgurl" maxlength="255" />
						<label for="edit_show_date">Show Date</label>
						<input type="text" name="edit_show_date" class="dhdatepicker" id="edit_show_date" value="$showdate" /><br />
						<label for="edit_expiration_date">Expiration Date</label>
						<input type="text" name="edit_expiration_date" class="dhdatepicker" id="edit_expiration_date" value="$expire" /><br />
						<label for="edit_valid">Valid?</label>
						<select name="edit_valid" id="edit_valid">
							$selectValidOptions
						</select><br />
						<label for="edit_invalidreason">Invalid Reason</label>
						<input type="text" name="edit_invalidreason" id="edit_invalidreason" value="$invalidreason" maxlength="255" /><br />
						<label for="edit_subject">Subject</label>
						<input type="text" name="edit_subject" id="edit_subject" value="$subject" /><br />
						<label for="edit_brief">Description</label>
						<textarea name="edit_brief" id="brief" rows="5">$brief</textarea>
					</p>
					<div class="form_button_div">
						<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_dailynotes('{$scriptName}', {$module});" />
						<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
					</div>
					<input type="hidden" name="dealid" id="edit_dealid" value="$id" />
					<input type="hidden" name="task" id="edit_task" value="$task" />
					<input type="hidden" name="module" value="$module" />
				</form>
			</td>
		</tr>
	</table>
HEREDOC;
?>