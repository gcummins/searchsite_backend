<?php

require_once "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];

if (isset($_REQUEST['banner_id']) && !empty($_REQUEST['banner_id']))
{
	// This is an edit request
	$id = (int)$_REQUEST['banner_id'];
	
	$query = "SELECT"
		. " `name`, `link_url`, `image_url`, `tracker_url`, `alt_text`,"
		. " `height`, `width`, `open_new_window`, `default`"
		. " FROM `" . DEALHUNTING_BANNERS_TABLE . "` WHERE `id`=$id LIMIT 1;";
	$dealhuntingDatabase->query($query, false);
	
	if ($dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
		exit();
	}
	
	$row = $dealhuntingDatabase->firstObject();
	
	$formTitle			= "Edit Banner";
	$name				= htmlentities(stripslashes($row->name));
	$link_url			= htmlentities(stripslashes($row->link_url));
	$image_url			= htmlentities(stripslashes($row->image_url));
	$tracker_url		= htmlentities(stripslashes($row->tracker_url));
	$alt_text			= htmlentities(stripslashes($row->alt_text));
	$height				= (int)$row->height;
	$width				= (int)$row->width;
	$open_new_window	= ($row->open_new_window == 1) ? 1 : 0;
	$default			= ($row->default == 1) ? 1 : 0;
	$task				= 'saveEdit';
}
else
{
	// This will be a new banner
	$id					= null;
	$formTitle			= "Create Banner";
	$name				= '';
	$link_url			= '';
	$image_url			= '';
	$tracker_url		= '';
	$alt_text			= '';
	$height				= BANNER_DEFAULT_HEIGHT;
	$width				= BANNER_DEFAULT_WIDTH;
	$open_new_window	= 1;
	$default			= 0;
	$task				= 'saveNew';
}

// Create the "open in new window" select options
$selectOpenNewWindowOptions = "<option value=\"1\"";
if ($open_new_window)
{
	$selectOpenNewWindowOptions .= " selected=\"selected\"";
}
$selectOpenNewWindowOptions .= ">Yes</option><option value=\"0\"";
if (!$open_new_window)
{
	$selectOpenNewWindowOptions .= " selected=\"selected\"";
}
$selectOpenNewWindowOptions .= ">No</option>";

// Create the "default" select options
$selectDefaultOptions = "<option value=\"1\"";
if ($default)
{
	$selectDefaultOptions .= " selected=\"selected\"";
}
$selectDefaultOptions .= ">Yes</option><option value=\"0\"";
if (!$default)
{
	$selectDefaultOptions .= " selected=\"selected\"";
}
$selectDefaultOptions .= ">No</option>";

$adminPath = ADMINPANEL_WEB_PATH;

echo <<< HEREDOC
<span class="edit_div_title">$formTitle</span>
	<table>
		<tbody>
		<tr>
			<td class="detail_cell">
				<form action="$scriptName" method="post">
					<div id="section_container">
						<label>Name</label>
						<input type="text" name="banner_name" id="banner_name" value="$name" maxlength="128" /><br />
						<label>Link URL</label>
						<input type="text" name="banner_link_url" id="banner_link_url" value="$link_url" maxlength="1024" /><br />
						<label>Image URL</label>
						<input type="text" name="banner_image_url" id="banner_image_url" value="$image_url" maxlength="1024" /><br />
						<label>Tracker URL</label>
						<input type="text" name="banner_tracker_url" id="banner_tracker_url" value="$tracker_url" maxlength="1024" /><br />
						<label>Alt Text</label>
						<input type="text" name="banner_alt_text" id="banner_alt_text" value="$alt_text" maxlength="512" /><br />
						<label>Height (in pixels)</label>
						<input type="text" name="banner_height" id="banner_height" value="$height" maxlength="4" /><br />
						<label>Width (in pixels)</label>
						<input type="text" name="banner_width" id="banner_width" value="$width" maxlength="4" /><br />
						<label>Open In New Window?</label>
						<select name="banner_open_new_window" id="banner_open_new_window">
							$selectOpenNewWindowOptions
						</select><br />
						<label>Default?</label>
						<select name="banner_default" id="banner_default">
							$selectDefaultOptions
						</select>
					</div>
					<div class="form_button_div">
						<input value="Submit" onclick="return validateForm_banners('$adminPath/');" type="submit">
						<input value="Cancel" onclick="hideEditDiv();" type="button">
					</div>
					<input name="banner_id" id="banner_id" value="$id" type="hidden" />
					<input name="task" id="redirection_task" value="$task" type="hidden" />
					<input name="module" id="module" value="$module" type="hidden" />
				</form>
			</td>
		</tr>
		</tbody>
	</table>
HEREDOC;
?>