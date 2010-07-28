<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

if (isset($_REQUEST['couponid']) && (int)$_REQUEST['couponid'])
{
	$id = (int)$_REQUEST['couponid'];
	
	$query = "SELECT `company`, `joblo_url`, `aff_url`, `_desc` AS `description`, `url`, `clean_url`, `_show`, `enable`, `expire`, `code`, `who`, `_when`, `notes`, `status` FROM " . DEALHUNTING_COUPONS_TABLE . " WHERE id=$id LIMIT 1;";
	$dealhuntingDatabase->query($query, false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
	}
	if ($dealhuntingDatabase->rowCount() != 0)
	{
		$row = $dealhuntingDatabase->firstObject();
	}
	$company		= intval($row->company);
	$joblo_url		= htmlentities(stripslashes($row->joblo_url));
	$aff_url		= htmlentities(stripslashes($row->aff_url));
	$description	= htmlentities(stripslashes($row->description));
	$url			= htmlentities(stripslashes($row->url));
	$clean_url		= htmlentities(stripslashes($row->clean_url));
	$show			= intval($row->_show);
	$startDate		= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->enable));
	if (empty($row->expire) || $row->expire == '0000-00-00')
	{
		$endDate = null;
	}
	else
	{
		$endDate		= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->expire));
	}
	$code			= htmlentities(stripslashes($row->code));
	$who			= htmlentities(stripslashes($row->who));
	$notes			= htmlentities(stripslashes($row->notes));
	$status			= intval($row->status);
	
	$task			= 'editExisting';
	$formTitle		= "Edit Coupon";
}
else
{
	$id				= null;
	
	$company		= null;
	$joblo_url		= '';
	$aff_url		= '';
	$description	= '';
	$url			= '';
	$clean_url		= '';
	$show			= 1;
	$startDate		= date(ADMINPANEL_DATE_FIELD_FORMAT, time());
	$endDate		= null;
	$code			= '';
	$notes			= '';
	$status			= 0; // Enabled by default
	
	$task			= 'addNew';
	$formTitle		= "Create New Coupon";
}

// TODO: Determine what the GBUCheck does in the existing admin panel, and duplicate the functionality if needed.

// Create ths 'companies' select options
$query = "SELECT `id`, `company` FROM " . DEALHUNTING_COMPANIES_TABLE . " ORDER BY `company` ASC;";
$dealhuntingDatabase->query($query, false);
if (true === $dealhuntingDatabase->error)
{
	returnError(902, $query, false, $dealhuntingDatabase, 'ajax');
}
else if ($dealhuntingDatabase->rowCount() != 0)
{
	$editCompanySelectOptions = "<option value=\"\">Select One...</option>\n";
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$editCompanySelectOptions .= '<option value="' . $row->id . '"';
		if ($company == $row->id)
		{
			$editCompanySelectOptions .= ' selected="selected"';
		}
		$editCompanySelectOptions .= ">" . $row->company . "</option>\n";
	}
}
else
{
	$editCompanySelectOptions = '<option value="">* No Companies Found *</option>';
}

// Create the 'show' select options
$editShowSelectOptions = '<option value="1"';
if ($show)
{
	$editShowSelectOptions .= ' selected="selected"';
}
$editShowSelectOptions .= ">Yes</option>\n";
$editShowSelectOptions .= '<option value="0"';
if (!$show)
{
	$editShowSelectOptions .= ' selected="selected"';
}
$editShowSelectOptions .= ">No</option>\n";


// Create the 'status' select options
$editStatusSelectOptions = '<option value="0"';
if ($status == 0)
{
	$editStatusSelectOptions .= ' selected="selected"';
}
$editStatusSelectOptions .= ">Good</option>\n" . '<option value="1"';
if ($status == 1)
{
	$editStatusSelectOptions .= ' selected="selected"';
}
$editStatusSelectOptions .= ">Bad</option>\n" . '<option value="2"';
if ($status == 2)
{
	$editStatusSelectOptions .= ' selected="selected"';
}
$editStatusSelectOptions .= ">Maybe</option>\n" . '<option value="3"';
if ($status == 3)
{
	$editStatusSelectOptions .= ' selected="selected"';
}
$editStatusSelectOptions .= ">Unusable</option>\n";

// Assigning the constant's value to a variable so that it can be used in the HEREDOC:
$datepickerFieldFormat = ADMINPANEL_DATEPICKER_DATE_FIELD_FORMAT;

echo <<< HEREDOC
<span class="edit_div_title">$formTitle</span>
<table>
	<tr>
		<td class="detail_cell">
			<form action="$scriptName" method="post">
				<div id="section_container">
					<label>Code</label>
					<input type="text" name="edit_code" id="edit_code" value="$code" maxlength="50" /><br />
					<label>Description</label>
					<input type="text" name="edit_description" id="edit_description" value="$description" maxlength="255" /><br />
					<label>Company</label>
					<select name="edit_company" id="edit_company">
						$editCompanySelectOptions;
					</select><br />
					<label>Status</label>
					<select name="edit_status" id="edit_status">
						$editStatusSelectOptions;
					</select><br />
					<label>URL</label>
					<input type="text" name="edit_url" id="edit_url" value="$url" maxlength="500" /><br />
					<label>CleanURL</label>
					<input type="text" name="edit_clean_url" id="edit_clean_url" value="$clean_url" maxlength="255" /><br />
					<label>JoBlo URL</label>
					<input type="text" name="edit_joblo_url" id="edit_joblo_url" value="$joblo_url" maxlength="255" /><br />
					<label>Generic Affiliate URL</label>
					<input type="text" name="edit_aff_url" id="edit_aff_url" value="$aff_url" maxlength="255" /><br />
					<label>Notes</label>
					<textarea name="edit_notes" id="edit_notes" rows="3" cols="10">$notes</textarea><br />
					<label>Start Date</label>
					<input type="text" name="edit_start_date" class="dhdatepicker" id="edit_start_date" value="$startDate" /><br />
					<label>Expiration Date</label>
					<input type="text" name="edit_end_date" class="dhdatepicker" id="edit_end_date" value="$endDate" /><br />
					<label>Show?</label>
					<select name="edit_show" id="edit_show">
						$editShowSelectOptions;
					</select>
				</div>
				<div class="form_button_div">
					<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_coupons();" />
					<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
				</div>
				<input type="hidden" name="task" id="task" value="$task" />
				<input type="hidden" name="couponid" id="couponid" value="$id" />
				<input type="hidden" name="module" value="$module" />
			</form>
		</td>
	</tr>
</table>
HEREDOC;
?>