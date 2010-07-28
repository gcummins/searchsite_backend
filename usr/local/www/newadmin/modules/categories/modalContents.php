<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

if (isset($_REQUEST['categoryid']))
{
	// This is an edit request
	$id			= (int)$_REQUEST['categoryid'];
	$formTitle	= "Edit Category";
	
	$query = "SELECT `cat`, `banner_id`, `benddate`, `orderby`,"
		. " `" . DEALHUNTING_BANNERS_TABLE . "`.`name` as `bannerName`"
		. " FROM " . DEALHUNTING_CATEGORIES_TABLE
		. " LEFT JOIN `banners` ON `" . DEALHUNTING_BANNERS_TABLE . "`.`id` = "
		. " `" . DEALHUNTING_CATEGORIES_TABLE . "`.`banner_id`"
		. " WHERE `" . DEALHUNTING_CATEGORIES_TABLE . "`.`id`=$id LIMIT 1;";
		
	$dealhuntingDatabase->query($query, false);
	
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
	}
	
	if ($dealhuntingDatabase->rowCount() == 0)
	{
		// A record with the requested ID was not found.
		returnError(201, 'No category matching that ID was found.', false, null, 'ajax');
		exit();
	}

	$row		= $dealhuntingDatabase->firstObject();
	$category	= stripslashes(htmlspecialchars($row->cat));
	//$banner		= htmlspecialchars(stripslashes($row->banner));
	$banner_id	= (int)$row->banner_id;
	if ($banner_id)
	{
		$bannerName	= htmlspecialchars($row->bannerName);
	}
	else
	{
		$bannerName = "None";
	}
	if ($row->benddate == '0000-00-00 00:00:00')
	{
		$date = '';
	}
	else
	{
		$date		= date(ADMINPANEL_DATE_FIELD_FORMAT, strtotime($row->benddate));
	}
	$orderby	= (int)$row->orderby;

	$task		= 'editSubmit';
}
else
{
	// This is a new category
	$formTitle	= "Create Category";
	$category	= '';
	//$banner		= '';
	$banner_id	= null;
	$bannerName	= "None";
	$date		= '';
	$task		= 'addSubmit';
	$id			= null;
	$orderby	= null;
}

// Determine the highest "order by" value
$query = "SELECT max(`orderby`) AS `max_orderby` FROM `" . DEALHUNTING_CATEGORIES_TABLE . "`;";
$dealhuntingDatabase->query($query, false);
if (true === $dealhuntingDatabase->error)
{
	returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
}
$arrOrderByResult = $dealhuntingDatabase->firstArray();
$maxOrderby = $arrOrderByResult['max_orderby'];

// Create a select list for reordering the categories
$query = "SELECT `id`, `cat`, `orderby` FROM `" . DEALHUNTING_CATEGORIES_TABLE . "` ORDER BY `orderby` ASC;";
$dealhuntingDatabase->query($query);

if (true === $dealhuntingDatabase->error)
{
	returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
}

$selectedIsSet = false;
$editOrderSelectOptions = '		<option value="first"';
if ($orderby == 1)
{
	$editOrderSelectOptions .= ' selected="selected"';
	$selectedIsSet = true;
}
$editOrderSelectOptions .= '>First</option>';

foreach ($dealhuntingDatabase->objects() as $row)
{
	if ($row->orderby == 1)
	{
		continue;
	}
	
	$editOrderSelectOptions .= '
		<option value="' . $row->orderby . '"';
	
	if ($selectedIsSet == false)
	{
		if ($row->orderby == $orderby && $orderby == $maxOrderby)
		{
			$editOrderSelectOptions .= " selected=\"selected\">Last</option>";
		}
		else
		{
			if ($orderby != null && (int)$row->orderby == $orderby-1)
			{
				$editOrderSelectOptions .= " selected=\"selected\"";
			}
			$editOrderSelectOptions .= '>after ' . $row->cat . '</option>';
		}
	}
	else
	{
		$editOrderSelectOptions .= '>after ' . $row->cat . '</option>';
	}
}

$adminPath = ADMINPANEL_WEB_PATH;

echo <<< HEREDOC1
<span class="edit_div_title">$formTitle</span>
<table>
	<tr>
		<td class="detail_cell">
			<form action="$scriptName" method="post">
				<div id="section_container">
					<label>Category</label>
					<input type="text" name="edit_category" id="edit_category" value="$category" /><br />
					<label>Order</label>
					<select name="edit_order" id="edit_order">
						$editOrderSelectOptions;
					</select><br />
					<label>Banner</label>
					<span class="marginLikeInput" id="banner_name_span">$bannerName</span>&nbsp;<a href="#" class="simulateInputMargins" onclick="showBannerSelection('$adminPath', '$banner_id');">Change...</a><br />
					<label>Banner Expiration</label>
					<input type="text" name="edit_expiration_date" class="dhdatepicker" id="edit_expiration_date" value="$date" /><br />
				</div>
				<div class="form_button_div">
					<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_categories();" />
					<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
				</div>
				<input type="hidden" name="banner_id" id="banner_id" value="$banner_id" />
				<input type="hidden" name="task" id="task" value="$task" />
				<input type="hidden" name="categoryid" id="categoryid" value="$id" />
				<input type="hidden" name="module" value="$module" />
			</form>
		</td>
	</tr>
</table>
HEREDOC1;
?>