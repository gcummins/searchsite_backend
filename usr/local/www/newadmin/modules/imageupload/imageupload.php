<?php

// Make sure this script is not called directly
if (!defined('APP_NAME'))
{
	die("This script cannot be accessed directly.");
}

$task = getTask();

switch ($task)
{
	case "submit":
		formSubmitted();
		break;
	default:
		showTable();
		break;
}

function showTable()
{
	global $module;
	
	if (!isPermitted('edit', $module))
	{
		// redirect to the main page of the application
		$_SESSION['sysmessage'] = LANGUAGE_ERROR_OPERATION_NOT_PERMITTED;
		$_SESSION['sysmtype'] = 'error';
		
		session_write_close();
		?>
		<script type="text/javascript">
		location.href="<?php echo ADMINPANEL_WEB_PATH; ?>";
		</script>
		<?php
		exit();
	}
	
	?><form action="<?php echo $_SERVER['PHP_SELF']; ?>?module=<?php echo $module; ?>&task=submit" method="post" enctype="multipart/form-data">
	<label for="userfile">File to upload</label><input type="file" class="text" name="userfile" /><input type="submit" value="Submit" name="submit" />
	</form>
	<?php
}

function formSubmitted()
{
	global $module;
	
	if (!isset($_FILES['userfile']))
	{
		returnError(201, "No image was provided", false);
		returnToMainPage();
		exit;
	}
	
	if (is_uploaded_file($_FILES['userfile']['tmp_name']))
	{
		if ($_FILES['userfile']['type'] == 'image/gif' || $_FILES['userfile']['type'] == 'image/jpg' || $_FILES['userfile']['type'] == 'image/pjpg' || $_FILES['userfile']['type'] == 'image/png')
		{
			if (file_exists(DEALHUNTING_GALLERY_FILESYSTEM_PATH . '/' . $_FILES['userfile']['name']))
			{
				returnError(201, "A file with this name already exists.", false);
				returnToMainPage();
				exit;
			}
			
			if (!move_uploaded_file($_FILES['userfile']['tmp_name'], DEALHUNTING_GALLERY_FILESYSTEM_PATH . '/' . $_FILES['userfile']['name']))
			{
				returnError(201, "The file upload failed.", false);
			}
			else
			{
				returnMessage(1001, "Image '" . $_FILES['userfile']['tmp_name'] . "' was uploaded successfully", true);
				returnToMainPage();
			}
		}
		else
		{
			returnError(201, "The file type is not a valid image. Please upload images in JPG, GIF, or PNG format.", true);
			returnToMainPage();
			exit;
		}
	}
}
?>