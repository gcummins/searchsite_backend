<?php

define('APP_NAME', 'DH Admin');

session_start();

// Include the configuration settings
include_once "configuration.php";
include_once "newconfig.php";

include_once "includes/db.class.php";

// Include generic, multipurpose functions
include_once "includes/functions.php";

// Include the functions to verify that the user is authenticated
include_once "includes/authuser.php";

// Include the logging functions
include_once "includes/log.php";

// Connect to the database server
include_once "includes/connect.php";

// Include the error_handling functions
include_once "includes/errorHandler.php";

// Include the module error handling functions
include_once "modules/includes/error_handler.php";

// Include the permission-checking functions
include_once "includes/permissions.php";


// Connect to the databases
$dealhuntingDatabase = new DatabaseConnection(DEALHUNTING_DB_HOST, DEALHUNTING_DB_USERNAME, DEALHUNTING_DB_PASSWORD, DEALHUNTING_DB_NAME);
$adminDatabase = new DatabaseConnection(ADMINPANEL_DB_SERVER, ADMINPANEL_DB_USERNAME, ADMINPANEL_DB_PASSWORD, ADMINPANEL_DB_NAME);
$feedDatabase = new DatabaseConnection(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);

// Determine if a specific module has been requested
$module = false;
$page = false;
if (isset($_REQUEST['module']) && !empty($_REQUEST['module']))
{
	$module = (int)$_REQUEST['module'];
	
	// Determine if the user is allowed to access this module
	if (isPermitted('view', $module))
	{
		// Get the name of the requested module
		$query = "SELECT `name` FROM modules WHERE id=$module LIMIT 1;";
		
		$result = mysql_query($query, $adminLink) or returnError(902, $query, 'true', $adminLink);
		$row = mysql_fetch_object($result);
	
		if (!mysql_num_rows($result))
		{
			returnError(777, "The requested module does not exist, or the 'modules' table in the database contains inaccurate information for module $module. Please contact an administrator.", false);
			returnToMainPage();
		}
		
		$moduleName = $row->name;
		
		// Determine if a CSS file exists and is readable for this module.
		$cssfilename = ADMINPANEL_APP_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . '/css/style.css';
		$cssfilename_ie6 = ADMINPANEL_APP_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . '/css/style_ie6.css';
		$cssIncludeString = '';
		if (is_readable($cssfilename))
		{
			$cssIncludeString = "<link rel=\"stylesheet\" href=\"" . ADMINPANEL_WEB_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . '/css/style.css' . "\" type=\"text/css\" />";
		}
		if (is_readable($cssfilename_ie6))
		{
			$cssIncludeString .= "\n<!--[if lt IE 7]>"
				. "\n\t<link rel=\"stylesheet\" type=\"text/css\" href=\"" . ADMINPANEL_WEB_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . "/css/style_ie6.css\" />"
				. "\n<![endif]-->";
		}
		
		// Determine if a Javascript file exists and is readable for this module.
		$jsfilename = ADMINPANEL_APP_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . '/js/script.js';
		if (is_readable($jsfilename))
		{
			$jsIncludeString = "<script type=\"text/javascript\" src=\"" . ADMINPANEL_WEB_PATH . '/' . ADMINPANEL_MODULE_DIRECTORY . '/' . $moduleName . '/js/script.js' . "\"></script>";
		}
	}
	else
	{
		// The user is not allowed to access this module. Set the $module
		// variable to null so that the default page will be displayed
		$module = null;
	}
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>DealHunting Administration</title>
<?php
	// Load an alternate theme, if available
	if (is_file(ADMINPANEL_APP_PATH . '/css/' . ADMINPANEL_THEME . '.css'))
	{
		?>	<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/<?php echo ADMINPANEL_THEME; ?>.css" />
		<?php
		if (is_file(ADMINPANEL_APP_PATH . '/css/' . ADMINPANEL_THEME . '_ie6.css'))
		{
			// Load an IE6-specific stylesheet for this theme
			?>
	<!--[if lt IE 7]>
	<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/<?php echo ADMINPANEL_THEME; ?>_ie6.css" />
	<![endif]-->
	<!--[if IE]>
	<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/<?php echo ADMINPANEL_THEME; ?>_ie.css" />
	<![endif]-->
			<?php
		}
	}
	else
	{
		// Load the default theme
		?>	<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/index.css" />
		<?php
		if (is_file(ADMINPANEL_APP_PATH . '/css/index_ie6.css'))
		{
			// Load an IE6-specific stylesheet
			?>
			
	<!--[if lt IE 7]>
		<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/index_ie6.css" />
	<![endif]-->
	<!--[if IE]>
	<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/index_ie.css" />
	<![endif]--><?php
		}
	}
?>
	
	<link rel="stylesheet" type="text/css" href="<?php echo ADMINPANEL_WEB_PATH; ?>/css/jquery-ui-themeroller.css" />
<?php
if (isset($cssIncludeString))
{
	echo "$cssIncludeString\n";
}
?>
	<script type="text/javascript" src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/jquery.min.js"></script>
	<script type="text/javascript" src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/jquery-ui-personalized-1.5.2.min.js"></script>
	<script type="text/javascript" src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/XMLHttpRequest.js"></script>
	<script type="text/javascript" src="<?php echo ADMINPANEL_WEB_PATH; ?>/js/index.js"></script>
	<?php
	if (isset($jsIncludeString))
	{
		echo "$jsIncludeString\n";
	}
	?>
	<script type="text/javascript">
		window.onload = rewriteExternalLinks;
<?php

/* Menu Switching Code */
if (ADMINPANEL_MENU_TYPE == 'outlook')
{
	?>
var arrMenuHeadings = Array();
var currentMenuIndex = false;
	<?php

	// Load a list of available menus
	$query = "SELECT id, name, display_name FROM " . DB_NAME . ".menu_sections ORDER BY `order` ASC;";
	if (false === ($result = mysql_query($query, $adminLink)))
	{
		//logError('Loading Menu Sections', '', $adminLink, true, mysql_error($adminLink));
		returnError(902, $query, true, $adminLink);
	}
	$counter = 0;
	while($row = mysql_fetch_object($result))
	{
		?>arrMenuHeadings[<?php echo $counter; ?>] = new objMenuHeadings(<?php echo $row->id; ?>, '<?php echo $row->name; ?>', '<?php echo $row->display_name; ?>');
		<?php
		$counter++;
	}
}
/* End Menu Switching Code */
?>
	</script>
</head>
<body >
	<table class="bodytable">
		<tr id="bluebar_tr">
			<td colspan="2">
				<div id="bluebar" style="background: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/bar.gif') repeat-x top left;">
					<a href="logout.php"><img id="bluebar_close" src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/close.gif" alt="Close Window"/></a>
					<img id="bluebar_question" src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/question.gif" alt="Access Help" />
					<img id="bluebar_icon" src="<?php echo ADMINPANEL_WEB_PATH; ?>/images/icon.gif" alt="DealHunting Icon"/>
					<span id="bluebar_title">Adlistings Control Panel</span>
				</div>
			</td>
		</tr>
		<tr>
			<td id="td_leftbar_container" valign="top" style="height: <?php echo ($_SESSION['page_height'] - 33); ?>px"><?php
				/* Menu Switching Code, part two */
				if (ADMINPANEL_MENU_TYPE == 'outlook')
				{
					?>
				<table id="leftbar">
					<!-- The following lines are simply to make this valid XHTML. A table must include at least one set of TR and TD tags -->
					<tr>
						<td></td>
					</tr>
				</table><?php
				}
				else
				{
					?>
				<div id="leftbar">&nbsp;</div><?php
				}
				/* End Menu Switching Code, part two */
			?>
			</td>
			<td id="pagecontent" valign="top">
				<div id="errorPane">
					<p id="errorPaneText"></p>
					<span id="errorRightCorner">&nbsp;</span>
					<span id="errorLeftCorner">&nbsp;</span>
				</div><?php
				// Determine which page should be loaded.
				// We will first look up the module number provided, and load the 
				// default page for that module.

				$pageFound = false;
				if ($module)
				{
					// Get the module name and the default page
					$query = "SELECT name, display_name FROM modules WHERE id=$module LIMIT 1;";
					$result = mysql_query($query, $adminLink) or handle_error("Query failed: $query. MySQL said: <em>" . mysql_error($adminLink) . "</em>");
					
					if (mysql_num_rows($result))
					{
						// A module name was found
						$moduleRow = mysql_fetch_object($result);
						$moduleName = $moduleRow->name;
						$moduleDisplayName = $moduleRow->display_name;
						
						// Now attempt to get the default page for this module
						$query = "SELECT script_name, title FROM modules_pages WHERE module_id=$module AND `default`=1 LIMIT 1;";
						$result = mysql_query($query, $adminLink) or handle_error("Query failed: $query. MySQL said: <em>" . mysql_error($adminLink) . "</em>");
						if (mysql_num_rows($result))
						{
							$pageRow = mysql_fetch_object($result);
							$pageName = $pageRow->script_name;
							
							$pageFound = true;
						}
					}
				}
				
				if ($pageFound)
				{
					// Include the script that contains this module.
					include ADMINPANEL_APP_PATH . '/modules/' . $moduleName . '/' . $pageName;
				}
				else if (isset($_REQUEST['httpderror']) && (int)$_REQUEST['httpderror'])
				{
					switch ((int)$_REQUEST['httpderror'])
					{
						case 404:
							echo "The page you requested was not found. Please check the URL and try again, or use one of the links on the left.";
							break;
						case 403:
							echo "This application encountered a Permission Denied error while attempting to process your request.";
							break;
						case 500:
							echo "This application encountered an internal script error while attempting to process your request.";
							break;
						default:
							echo "An unknown HTTP error was specified. Please use one of the links on the left to access this application.";
							break;
					}
				}
				else
				{
					?>

				<div id="logo" style="background: url('<?php echo ADMINPANEL_WEB_PATH; ?>/images/dh.jpg') no-repeat left top;">
					<!-- <span id="business_name">Batea</span><br />
					<span id="logo_tag"><?php //echo $tagline; ?></span> -->
				</div><?php
				}
				
			// Start error message display processing
			$sysmessage = null;
			$sysmtype = 'error';
			if (array_key_exists('sysmessage', $_SESSION) && !empty($_SESSION['sysmessage']))
			{
				$sysmessage = $_SESSION['sysmessage'];
				// Clear the message so it is not displayed again
				unset($_SESSION['sysmessage']);
				
				if (array_key_exists('sysmtype', $_SESSION) && $_SESSION['sysmtype'] == 'info')
				{
					$sysmtype = 'information';
					// Clear the entry so it is not used again
					unset($_SESSION['sysmtype']);
				}
			}
			elseif (array_key_exists('sysmessage', $_REQUEST) && !empty($_REQUEST['sysmessage']))
			{
				$sysmessage = htmlentities($_REQUEST['sysmessage']);
				if (array_key_exists('sysmtype', $_REQUEST) && !empty($_REQUEST['sysmtype']))
				{
					if ($_REQUEST['sysmtype'] == 'info')
					{
						$sysmtype = 'information';
					}
				}
			}
				// End error message display processing
				?>
				
				<script type="text/javascript">
					errorMessage = '<?php echo addslashes($sysmessage); ?>';
					errorType = '<?php echo $sysmtype; ?>';
					
					if (errorMessage != '')
					{
						showError(errorMessage, errorType, '<?php echo ADMINPANEL_WEB_PATH; ?>');
					}
				</script>
			</td>
		</tr>
	</table>
	<script type="text/javascript">
	<?php
		/* Menu Switching Code, part three */
		if (ADMINPANEL_MENU_TYPE == 'outlook')
		{
		?>
	createMenuBar(null, <?php echo ($module) ? $module : 'null'; ?>, '<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo $_SESSION['page_height']; ?>);
		<?php
		}
		else
		{
			
			// Determine the name of the active menu
			if ($module)
			{
				$query = "SELECT menu_sections.display_name as menuName FROM `modules`LEFT JOIN menu_sections on menu_sections.id=modules.menu_section WHERE modules.id=$module;";
			}
			else
			{
				$query = "SELECT display_name as menuName FROM `menu_sections` ORDER BY `order` LIMIT 1;";
			}
			$result = mysql_query($query, $adminLink) or returnError(902, $query, true, $adminLink);
			$row = mysql_fetch_object($result);
			$formattedMenuName = str_replace(array(' ', '.', '-'), '', $row->menuName);
		?>
	createMenuBar_css('<?php echo ADMINPANEL_WEB_PATH; ?>', <?php echo ($module) ? $module : 0; ?>, '<?php echo $formattedMenuName; ?>');<?php
		}
		/* End Menu Switching Code */
	?>
	
	</script>
	<!-- Fake modal dialog box, populated as needed by the modules. -->
		<div id="modalContainer">
			<div id="edit_div"></div>
			<div class="translucentbackground"></div>
		</div>
		<div id="modalContainer_banners">
			<div id="banner_selection_div"></div>
		</div>
	<!-- End Fake Modal Dialog Box, displayed as needed by the modules. -->
	<!-- "Script Busy" Indicator -->
		<div id="scriptBusyContainer">
			<div id="scriptBusyInnerDiv">
				<span id="scriptBusyOutput"></span><br />
				<div id="scriptBusyImageDiv"><img src="images/progress_bar.gif" alt="Loading -- Please Wait" /></div>
			</div>
		</div>
	<!-- End "Script Busy" Indicator -->
</body>
</html>
