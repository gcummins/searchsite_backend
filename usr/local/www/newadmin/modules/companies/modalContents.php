<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

if (isset($_REQUEST['companyid']) && !empty($_REQUEST['companyid']))
{
	// This is an edit request. Gather the information about this company from the database
	$formTitle	= "Edit Company";
	$task 		= "saveEdit";
	$companyId	= (int)$_REQUEST['companyid'];
	
	$query = "SELECT `company`, `url`, `nocoupon`, `banner_id`, `enddate`, `joblo_url`,"
		. " `aff_url`, `aff_type`, `clean_url`, `alert`, `canadaship`, `usship`, `ukship`, "
		. " `" . DEALHUNTING_BANNERS_TABLE . "`.`name` as `bannerName`"
		. " FROM " . DEALHUNTING_COMPANIES_TABLE
		. " LEFT JOIN `banners` ON `" . DEALHUNTING_BANNERS_TABLE . "`.`id` ="
		. " `" . DEALHUNTING_COMPANIES_TABLE . "`.`banner_id`"
		. " WHERE `" . DEALHUNTING_COMPANIES_TABLE . "`.`id`=$companyId LIMIT 1;";
	
	$dealhuntingDatabase->query($query, false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
	}
	
	$row = $dealhuntingDatabase->firstArray();
	
	foreach ($row as $fieldName => $fieldValue)
	{
		if ($fieldName == 'enddate')
		{
			// Format the date properly
			if (!empty($fieldValue) && $fieldValue != '0000-00-00 00:00:00')
			{
				$$fieldName = date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($fieldValue));
			}
			else
			{ 
				$$fieldName = '';
			}
		}
		else
		{
			$$fieldName = stripslashes($fieldValue);
		}
	}
	if ($banner_id == null)
	{
		$bannerName = "None";
	}
}
else
{
	// This is a new company
	$formTitle		= "Create Company";
	$task			= "saveNew";
	$company		= "";
	$url			= "";
	$banner			= "";
	$banner_id		= null;
	$bannerName		= "None";
	$enddate		= "";
	$joblo_url		= "";
	$aff_type		= 0;
	$clean_url		= "";
	$alert			= 0;
	$canadaship		= 0;
	$usship			= 1;
	$ukship			= 0;
	$companyId		= null;
}


// Get a list of affiliate_types
$dealhuntingDatabase->query("SELECT `affiliate_type_id`, `label`FROM `affiliate_type` ORDER BY `label` ASC;");

$affiliateOptions = "<option value=\"0\">None Selected</option>\n";

foreach ($dealhuntingDatabase->objects() as $affRow)
{
	$affiliateOptions .= "<option value=\"" . $affRow->affiliate_type_id . "\"";
	if ($aff_type == $affRow->affiliate_type_id)
	{
		$affiliateOptions .= " selected";
	}
	$affiliateOptions .= ">" . $affRow->label . "</option>\n";
}

if ($alert == "0")
{
	$alertStringNo  = " selected=\"selected\"";
	$alertStringYes = "";
}
else
{
	$alertStringNo  = "";
	$alertStringYes = " selected=\"selected\"";	
}

if ($usship == 1)
{
	$usShipCheckedString = "checked=\"checked\" ";
}
else
{
	$usShipCheckedString = "";
}

if ($ukship == 1)
{
	$ukShipCheckedString = "checked=\"checked\" ";
}
else
{
	$ukShipCheckedString = "";
}

if ($canadaship == 1)
{
	$canadaShipCheckedString = "checked=\"checked\" ";
}
else
{
	$canadaShipCheckedString = "";
}

$adminPath = ADMINPANEL_WEB_PATH;
echo <<<END
<span class="edit_div_title">$formTitle</span>
		<table width="98%">
			<tr>
				<td class="detail_cell">
					<form action="$scriptName" method="post">
					<ul id="edit_div_navigation">
						<li id="section_general_li" onclick="javascript:showSection('general');">General</li>
						<li id="section_url_li" onclick="javascript:showSection('url');">URLs</li>
						<li id="section_coupons_li" onclick="javascript:showSection('coupons');">Coupons</li>
						<li id="section_shipping_li" onclick="javascript:showSection('shipping');">Shipping</li>					
					</ul>
					
					<div id="section_container">
						<!-- Section: General -->
						<div id="section_general">
							<label>Company</label>
							<input type="text" name="edit_company" id="edit_company" value="$company" /><br />
							<label>Affiliate Type</label>
							<select name="edit_aff_type" id="edit_aff_type">
								$affiliateOptions
							</select><br />
							<label>Banner</label>
							<span class="marginLikeInput" id="banner_name_span">$bannerName</span>&nbsp;<a href="#" class="simulateInputMargins" onclick="showBannerSelection('$adminPath', '$banner_id');">Change...</a><br />
							<label>Banner Expiration</label>
							<input type="text" name="edit_expiration_date" class="dhdatepicker" id="edit_expiration_date" value="$enddate" /><br />
							<input type="hidden" id="aff_type_hidden_value" value="$aff_type" />
						</div>
						<!-- END Section: General -->
						<!-- Section: URL -->
						<div id="section_url">
							<label>URL</label>
							<input type="text" name="edit_url" id="edit_url" value="$url" /><br />
							<label>CleanURL</label>
							<input type="text" name="edit_clean_url" id="edit_clean_url" value="$clean_url" /><br />
							<label>JoBlo URL</label>
							<input type="text" name="edit_joblo_url" id="edit_joblo_url" value="$joblo_url" /><br />
						</div>
						<!-- ENDSection: URL -->
						<!-- Section: Coupons -->
						<div id="section_coupons">
							<label>No Coupon Alert</label>
							<select name="edit_alert" id="edit_alert">
								<option value="0" $alertStringNo>No</option>
								<option value="1" $alertStringYes>Yes</option>
							</select><br />
						</div>
						<!-- END Section: Coupons -->
						<!-- Section: Shipping -->
						<div id="section_shipping">
							<label>Ships to the U.S.?</label>
							<input type="checkbox" class="checkbox" name="edit_usship" id="edit_usship" $usShipCheckedString/><br />
							<label>Ships to Canada?</label>
							<input type="checkbox" class="checkbox" name="edit_canadaship" id="edit_canadaship" $canadaShipCheckedString/><br />
							<label>Ships to the U.K.?</label>
							<input type="checkbox" class="checkbox" name="edit_ukship" id="edit_ukship" $ukShipCheckedString/><br />
						</div>
						<!-- END Section: Shipping -->
					</div> <!-- END section_container -->
					<div class="form_button_div">
						<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_companies('{$scriptName}', {$module});" />
						<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
					</div>
					<input type="hidden" name="banner_id" id="banner_id" value="$banner_id" />
					<input type="hidden" name="edit_company_id" id="edit_company_id" value="$companyId" />
					<input type="hidden" name="task" id="edit_task" value="$task" />
					<input type="hidden" name="module" value="$module" />
					</form>
				</td>			
			</tr>		
		</table>
END;

?>