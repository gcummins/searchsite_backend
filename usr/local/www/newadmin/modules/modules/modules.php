<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

?>
<script type="text/javascript" language="Javascript">
function tester()
{
	alert("In tester()");
}
</script>
<?php 
$task = getTask();

switch ($task)
{
	case 'showmodule':
		showModule();
		break;
	case 'updateModule':
		saveModuleInformation();
		break;
	default:
		showTable();
		break;
}

function saveModuleInformation()
{
	// Gather the submitted form information
	$id = $_POST['module_id'];
	$name = $_POST['module_name'];
	$display_name = $_POST['module_display_name'];
	$enable_logging = ($_POST['module_enable_logging']) ? 1: 0;
	$menu_section_id = $_POST['module_menu_section'];
	
	$query = "UPDATE modules SET name='$name', display_name='$display_name', enable_logging=$enable_logging, menu_section=$menu_section_id WHERE id=$id;";
	echo $query;
	die();
	
}

function showModule()
{
	global $adminLink, $module;
	
	// Determine which module was requested
	if (isset($_REQUEST['showmod']) && !empty($_REQUEST['showmod']))
	{
		$moduleToShow = (int)$_REQUEST['showmod'];
	}
	else
	{
		header(ADMINPANEL_WEB_PATH);
		exit();
	}
	
	// Load information about this module
	$query = "SELECT id, name, display_name, enable_logging, menu_section, `order`, icon FROM modules WHERE id=$moduleToShow LIMIT 1;";
	$result = mysql_query($query) or handle_error($query, true, 'mysql', $adminLink);
	
	if (!mysql_num_rows($result))
	{
		handle_error("An error occurred while loading information for this module.");
	}
	$row=mysql_fetch_object($result);
	
	// Display a form to enable editing of this module.
	?>
	<form action="<?php echo $_SERVER['PHP_SELF']; ?>?module=<?php echo $module; ?>&task=updateModule" method="post">
	<h3>Edit this Module</h3>
	<label for="module_name">Name:</label>
		<input type="text" name="module_name" id="module_name" value="<?php echo $row->name; ?>"><br />
	<label for="module_display_name">Display Name:</label>
		<input type="text" name="module_display_name" value="<?php echo $row->display_name; ?>"><br />
	<label for="module_enable_logging">Enable Logging?</label>
		<input type="checkbox" name="module_enable_logging" class="checkbox" <?php echo ($row->enable_logging)?'checked':''; ?> /><br />
	<label for="module_menu_section">Menu Section</label>
		<select name="module_menu_section"><?php
		// Get a list of all available menu sections
		$query = "SELECT id, display_name FROM menu_sections ORDER BY `order` ASC;";
		$result = mysql_query($query) or handle_error($query, true, 'mysql', $adminLink);
		
		while ($menuSectionRow = mysql_fetch_object($result))
		{
			?>
			<option value="<?php echo $menuSectionRow->id; ?>"><?php echo $menuSectionRow->display_name; ?></option><?php
		}
		?>
		</select><br />
	<label for="module_order">Order</label>
		<span><em>Not Implemented Yet</em></span>
	<label for="submit">&nbsp;</label>
		<input type="submit" name="submit" value="Submit" /><br />
		<input type="hidden" name="module_id" value="<?php echo $row->id; ?>" />
	</form>
	<script type="text/javascript" language="Javascript">
	document.getElementById('module_name').focus();
	</script>

<table class="contentTable">
<tr class="table_titlebar">
	<td colspan=3>Pages in this Module</td>
</tr>
</table>
	<?php
}

function showTable()
{
	global $adminLink, $module;
	?>
<table class="contentTable">
<tr class="table_titlebar">
	<td colspan=3>Module Manager</td>
</tr>
<tr>
	<th>Module</th>
	<th>Logging Enabled</th>
	<th>Menu Section</th>
</tr>

<?php
// Get a list of all available modules
$query = "SELECT modules.id, modules.display_name, modules.enable_logging, modules.menu_section, menu_sections.display_name as menu_name FROM modules LEFT JOIN menu_sections ON modules.menu_section = menu_sections.id ORDER BY menu_name ASC, modules.`order` ASC;";
$result = mysql_query($query, $adminLink) or handle_error($query, true, 'mysql', $adminLink);

while ($row = mysql_fetch_object($result))
{
	?><tr onclick="location.href='<?php echo ADMINPANEL_WEB_PATH; ?>/?module=<?php echo $module; ?>&task=showmodule&showmod=<?php echo $row->id; ?>'">
	<td><?php echo $row->display_name; ?></td>
	<td><?php echo ($row->enable_logging)?'Yes':'No'; ?></td>
	<td><?php echo $row->menu_name; ?></td>
</tr><?php
}

?>
</table>
	<?php
}
?>
&nbsp;