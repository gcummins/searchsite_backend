<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

// Create the list of configuration sections handled by this module
switch ($module)
{
	case 45:
		$arrSections = array(array('topic' => 'imagecacheserver', 'label' => 'Image Server'));
		break;
	case 47:
		$arrSections = array(array('topic' => 'frontend', 'label' => 'Sub Sites'));
		break;
	case 46:
		$arrSections = array(array('topic' => 'searchsite', 'label' => 'Search Site'));
		break;
	case 48:
		$arrSections = array(array('topic' => 'adminpanel', 'label' => 'Admin Panel'));
		break;
	case 49:
		$arrSections = array(array('topic' => 'dealhunting', 'label' => 'DealHunting.com'));
		break;
	default:
		$arrSections = array(
			array('topic' => 'global', 'label' => 'General'),
			array('topic' => 'debug', 'label' => 'Debugging'),
			array('topic' => 'database', 'label' => 'Database'),
			array('topic' => 'logging', 'label' => 'Logging'),
			array('topic' => 'dataloader', 'label' => 'Dataloader'),
			array('topic' => 'linkshare', 'label' => 'LinkShare'),
			array('topic' => 'feedmapping', 'label' => 'Feedmapping')
						);
		break;
}

$task = getTask();

switch ($task)
{
	case 'submitChanges':
		submitChanges();
		break;
	default:
		showTable();
		break;
}

function createFormElement($configurationObject)
{
	// Element names are in the form of: config_<keyname>
	$formElementName = "config_" . strtolower($configurationObject->key);
	
	$output = "<label for=\"$formElementName\" title=\"" . $configurationObject->key . "\">";
	
	$output .= !empty($configurationObject->comment) ? $configurationObject->comment : $configurationObject->key;
	
	$output .= "</label>\n";

	switch ($configurationObject->type)
	{
		case "integer":
			$output .= "<input type=\"text\" class=\"integer\" name=\"$formElementName\" value=\"" . $configurationObject->value . "\" onchange=\"configurationForm_hideError()\" />";
			break;
		case "text":
			$output .= "<input type=\"text\" class=\"text\" name=\"$formElementName\" value=\"" . stripslashes($configurationObject->value) . "\" onchange=\"configurationForm_hideError()\" />";
			break;
		case "boolean":
			$output .= "<select class=\"select\" name=\"$formElementName\" onchange=\"configurationForm_hideError()\"><option value=\"yes\"";
			if ($configurationObject->value == "yes")
			{ 
				$output .= " selected";
			}
			$output .= ">Yes</option><option value=\"no\"";
			if ($configurationObject->value == "no")
			{
				$output .= " selected";
			}
			$output .= ">No</option></select>";
			break;
		default:
			return; // Return no output
			break;
	}
	
	return $output . "<br style=\"clear:both\"/>\n";
}

function showTable()
{
	global $feedDatabase, $module, $arrSections;
	
	returnMessage(1000, LANGUAGE_VIEWED_MAIN_PAGE, false);
	
	$feedDatabase->query("SELECT `key`, `value`, `type`, `comment` FROM `configuration` WHERE `topic`='global';");
	
	?><table class="contentTable">
	<form style="width: 100%" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
		<div class="configuration_form">
			<ul id="ul_configuration">
			<?php
				foreach ($arrSections as $arrSection)
				{
					$feedDatabase->query("SELECT `key`, `value`, `type`, `comment` FROM `configuration` WHERE `topic`='" . $arrSection['topic'] . "';");
					if ($feedDatabase->rowCount() != 0)
					{
					?><li><a href="#" onclick="configurationForm_showHide('<?php echo strtolower($arrSection['label']); ?>', this)"><?php echo $arrSection['label']; ?></a></li>
						<div id="configdiv_<?php echo strtolower($arrSection['label']); ?>">
						<?php
							
							
							foreach ($feedDatabase->objects() as $line)
							{
								echo createFormElement($line);
							}
						?>
						<input type="submit" class="submit" value="Save all" /><br style="clear: both" />
						</div>
					<?php
					}
				}
			?>
			</ul>
			<input type="hidden" name="module" value="<?php echo $module; ?>" />
			<input type="hidden" name="task" value="submitChanges" />
		</div>
	</form>
	</table>
	<?php
}

function submitChanges()
{
	global $feedDatabase;
	
	// Gather all of the form fields	
	$valuesFound = false;
	$queryWhenStatements = '';
	$queryKeys = '';

	foreach ($_POST as $fieldName=>$value)
	{
		if (substr($fieldName, 0, 7) == 'config_')
		{
			$keyName = strtoupper(substr($fieldName, 7));
			$valuesFound = true;
			$queryWhenStatements .= "when '$keyName' then '" . $feedDatabase->escape_string($value) . "' ";
			$queryKeys .= "'$keyName', ";
		}
	}
	
	
	if ($valuesFound)
	{
		// Update the database with the new field values.
		$query = "UPDATE `configuration` SET `value` = case `key` " . $queryWhenStatements . " end WHERE `key` IN (" . substr($queryKeys, 0, -2) . ");";
		$feedDatabase->query($query);
		
		generateConfigurationFile();
		
		returnMessage(1001, "Configuration has been saved.", true);
	}	
	
	returnToMainPage();
}

function generateConfigurationFile()
{
	// Create the configuration file from the records in the configuration database
	global $feedDatabase;
	
	$feedDatabase->query("SELECT `key`, `value`, `type` FROM `configuration`");
	
	if ($feedDatabase->rowCount() == 0)
	{
		return; // Nothing to do
	}
	
	$configFileString = "<?php\n/*\n *\n * This file is generated programmatically.\n * Any manual changes will be overwritten.\n *\n */\n";
	$configFileString .= "date_default_timezone_set('America/Chicago');\n";
	foreach ($feedDatabase->objects() as $line)
	{
					
		switch ($line->type)
		{
			case 'boolean':
				if ($line->value == 'yes')
					$outputValue = "true";
				else
					$outputValue = "false";
				break;
			case 'integer':
				$outputValue = (int)$line->value;
				break;
			case 'text':
			default:
				$outputValue = "\"" . $line->value . "\"";
				break;
		}
		$configFileString .= "define('" . $line->key . "', $outputValue);\n";
	}
	$configFileString .= "?>";
	
	
	/*
	 * The configuration file needs to be written to two locations:
	 * 
	 *  1. The Dataloader working directory
	 *  2. The administrative control panel working directory
	 */
	$feedDatabase->query("SELECT `value` FROM `configuration` WHERE `key`='GLOBAL_APP_PATH' or `key`='ADMINPANEL_APP_PATH' LIMIT 2;");
	foreach ($feedDatabase->objects() as $directory)
	{
		$fileHandle = fopen($directory->value . 'config.inc.php', 'w');
		fwrite($fileHandle, $configFileString);
		fclose($fileHandle);
	}
}
?>