<?php

require "../includes/backend_requirements.php";

$scriptName = htmlentities($_REQUEST['scriptname']);
$module = (int)$_REQUEST['module'];
$moduleName = getModuleName($module);

if (isset($_REQUEST['objectid']) && !empty($_REQUEST['objectid']))
{
	// This is an edit request.
	$formTitle	= "Edit Sub-Site";
	$task		= "saveEdit"; 
	$siteId	= (int)$_REQUEST['objectid'];
	
	// Gather the information about this subsite from the database.
	$query = "SELECT"
		. " `name`, `displayName`, `url`, `rotateProducts`, `databaseUsername`, `databasePassword`"
		. " FROM frontend_sites"
		. " WHERE `id`=$siteId LIMIT 1;";
	$feedDatabase->query($query);
	$row = $feedDatabase->firstArray();;
	
	// Assign each field value from the database to a dynamically-named variable
	foreach ($row as $fieldName => $fieldValue)
	{
		$$fieldName = stripslashes($fieldValue);
	}
	
	// Get the list of keywords for this subsite
	$feedDatabase->query("SELECT `keyword` FROM `frontend_sites_keywords` WHERE `site_id`=$siteId;");
	
	$keywordSelectOptions = '';
	foreach ($feedDatabase->objects() as $row)
	{
		$keywordSelectOptions .= "\n\t<option value=\"" . $row->keyword . "\">" . $row->keyword . "</option>";
	}
}
else
{
	// This is a new sub-site
	$formTitle		= "Create Sub-Site";
	$task			= "saveNew";
	$siteId		= 0;
	
	$name			= '';
	$displayName	= '';
	$url			= '';
	$databaseUsername	= '';
	$databasePassword	= '';
	
	$keywordSelectOptions = '';
}

$adminPath = ADMINPANEL_WEB_PATH;

echo <<< END
<span class="edit_div_title">$formTitle</span>
	<table>
		<tr>
			<td class="detail_cell">
				<form action="$scriptName" method="post">
					<ul id="edit_div_navigation">
						<li id="section_general_li" onclick="javascript:showSection('general');">General</li>
						<li id="section_database_li" onclick="javascript:showSection('database');">Database</li>
						<li id="section_topic_li" onclick="javascript:showSection('topic');">Topic</li>
					</ul>
					<div id="section_container">
						<!-- Section: General -->
						<div id="section_general">
							<label for="edit_name">Name</label>
							<input type="text" name="edit_name" id="edit_name" value="$name" maxlength="256" onkeyup="checkValue(this.value, 'name', $siteId, '$adminPath', '$moduleName');" /><span id="name_error" class="normal"></span><br />
							<label for="edit_name">Display Name</label>
							<input type="text" name="edit_display_name" id="edit_display_name" value="$displayName" maxlength="256" onkeyup="checkValue(this.value, 'displayname', $siteId, '$adminPath', '$moduleName');" /><span id="displayname_error" class="normal"></span><br />
							<label for="edit_url">URL</label>
							<input type="text" name="edit_url" id="edit_url" value="$url" maxlength="256" onkeyup="checkValue(this.value, 'url', $siteId, '$adminPath', '$moduleName');" /><span id="url_error" class="normal"></span><br />
						</div>
						<!-- End Section: General -->
						
						<!-- Section: Database -->
						<div id="section_database">
							<label for="edit_databaseusername">Username</label>
							<input type="text" name="edit_databaseusername" id="edit_databaseusername" value="$databaseUsername" maxlength="16" onkeyup="checkValue(this.value, 'databaseusername', $siteId, '$adminPath', '$moduleName');" /><span id="databaseusername_error" class="normal"></span><br />
							<label for="edit_databasepassword">Password</label>
							<input type="text" name="edit_databasepassword" id="edit_databasepassword" value="$databasePassword" maxlength="16" onkeyup="checkValue(this.value, 'databasepassword', $siteId, '$adminPath', '$moduleName');" /><span id="databasepassword_error" class="normal"></span><br />
						</div>
						<!-- End Section: Database -->
						
						<!-- Section: Topic -->
						<div id="section_topic">
							<label for="edit_keywords">Keywords<br /></label>
							<select name="edit_keywords[]" id="edit_keywords[]" multiple="multiple" size="5">
								$keywordSelectOptions
							</select><br />
							<div id="topic_buttons">
								<input type="button" class="topic_button_class" value="Add..." onclick="addKeyword();" />
								<input type="button" class="topic_button_class" value="Delete" onclick="removeKeyword();" />
							</div><br />
						</div>
						<!-- End Section: Topic -->
					</div>
					<div class="form_button_div">
						<input type="submit" value="Submit" class="inputbutton" onclick="return validateForm_subsites('{$scriptName}', {$module});" />
						<input type="button" value="Cancel" class="inputbutton" onclick="hideEditDiv();" />
					</div>
					<input type="hidden" name="site_id" id="edit_site_id" value="$siteId" />
					<input type="hidden" name="task" id="edit_task" value="$task" />
					<input type="hidden" name="module" value="$module" />
				</form>
			</td>
		</tr>
	</table>
END;
?>