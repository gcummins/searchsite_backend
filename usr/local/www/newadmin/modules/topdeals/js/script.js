function updateEditImage(imageField)
{	
	document.getElementById('editDivImage').src = imageField.value;
}
function showNewDiv(adminPath, scriptName, module, moduleName)
{	
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&modulename=' + moduleName;
	showDiv(url);
}
function showEditDiv(topdealId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&modulename=' + moduleName + '&topdealid=' + topdealId;
	showDiv(url)
}
function validateForm_topdeals(adminPath, moduleName)
{	
	var validSubmission = true;
	
	// Check the Link field
	if (document.getElementById('edit_link').value == '' || document.getElementById('edit_link').value == 'http://')
	{
		alert("A link must be provided for this Top Deal.");
		document.getElementById('edit_link').focus();
		return false;
	}
	else if ('http://' != document.getElementById('edit_link').value.substring(0, 7))
	{
		if (confirm("Top Deal links must begin with 'http://'. Shall I add it for you?"))
		{
			var existingString = document.getElementById('edit_link').value;
			document.getElementById('edit_link').value = 'http://' + existingString;
			
			validSubmission = validateURL('edit_link', 'link', adminPath, moduleName);
		}
		else
		{
			document.getElementById('edit_link').focus();
			return false;
		}
	}
	else
	{
		validSubmission = validateURL('edit_link', 'link', adminPath, moduleName);
	}
	
	if (!validSubmission)
	{
		alert("The Link URL is not valid. Please check the URL and try again.");
		document.getElementById('edit_link').focus();
		return false;
	}
	else
	{
		// Check the Image field
		if (document.getElementById('edit_image').value == '' || document.getElementById('edit_image').value == 'http://')
		{
			alert("An image URL must be provided for this Top Deal.");
			document.getElementById('edit_image').focus();
			return false;
		}
		else if ('http://' != document.getElementById('edit_image').value.substring(0, 7))
		{
			if (confirm("Top Deal image URLs must begin with 'http://'. Shall I add it for you?"))
			{
				var existingString2 = document.getElementById('edit_image').value;
				document.getElementById('edit_image').value = 'http://' + existingString2;
			
				validSubmission = validateURL('edit_image', 'image link', adminPath, moduleName);
			}
			else
			{
				document.getElementById('edit_image').focus();
				return false;
			}
		}
		else
		{
			validSubmission = validateURL('edit_image', 'image link', adminPath, moduleName);
		}
		
		if (document.getElementById('edit_impression').value != '') // Do not bother checking if nothing is in this field
		{
			if ('http://' != document.getElementById('edit_impression').value.substring(0, 7))
			{
				if (confirm("Hit Tracker URLs must begin with 'http://'. Shall I add it for you?"))
				{
					var existingString3 = document.getElementById('edit_impression').value;
					document.getElementById('edit_impression').value = 'http://' + existingString3;
					
					validSubmission = validateURL('edit_impression', 'hit tracker', adminPath, moduleName);
				}
				else
				{
					document.getElementById('edit_impression').focus();
					return false;
				}
			}
			else
			{
				validSubmission = validateURL('edit_impression', 'hit tracker', adminPath, moduleName);
			}
		}
		
		if (!validSubmission)
		{
			alert("The Hit Tracker URL is not valid. Please check the URL and try again.");
			document.getElementById('edit_impression').focus();
			return false;
		}
		else
		{
			return true;
		}
	}
}
function validateURL(formField, fieldName, adminPath, moduleName)
{
	var url = document.getElementById(formField).value;
	if (url == '')
	{
		alert("URL is empty. Returning...");
		return false;
	}
	
	xmlHttpValidateUrl = getXmlHttpObject();
	
	var date = new Date();
	var timestamp = date.getTime();
	
	validateResponseIsValid = false;
	var backendUrl = adminPath + '/modules/' + moduleName + '/checkValidURL.php?time=' + timestamp + '&url=' + encodeURIComponent(url);

	showAJAXWaitControl('Checking URL...');
	xmlHttpValidateUrl.open("GET",backendUrl,false);
	xmlHttpValidateUrl.send(null);
	hideAJAXWaitControl();
	
	var validateUrlResponseValue = xmlHttpValidateUrl.responseText;
	if (validateUrlResponseValue == '0')
	{
		validateResponseIsValid = true;
	}
	else
	{
		validateResponseIsValid = false;
	}
	return validateResponseIsValid;
}
function confirmDealDelete(imageURL, topdealId, adminPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you wish to delete this Top Deal?"))
	{
		var targetLocation = adminPath + '?module=' + module + '&task=deletead&topdealid=' + topdealId + '&paging_spage=' + startPage;
		location.href = targetLocation;
	}
}