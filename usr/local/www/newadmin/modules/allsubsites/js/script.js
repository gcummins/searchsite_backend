function addKeyword()
{
	var keyword = prompt("New Keyword?");
	
	if (keyword != '')
	{
		var outputLocation = document.getElementById('edit_keywords[]');
		
		var newOption = document.createElement('option');
		var newTextNode = document.createTextNode(keyword);
		
		newOption.value = keyword;
		newOption.appendChild(newTextNode);
		
		outputLocation.appendChild(newOption);
	}
}
function changeDate(dateRange, url)
{
	location.href = url + dateRange.value + '&datelabel=' + escape(dateRange.options[dateRange.selectedIndex].text);
}
function checkValue(fieldValue, fieldToCheck, subsiteId, adminPath, moduleName)
{
	theField = document.getElementById(fieldToCheck+'_error');
	
	// Do not check empty values
	if (fieldValue == '')
	{
		theField.className = 'hidden';
		theField.innerHTML = '';
		return;
	}
	
	// Ensure that URLs start with 'www.';
	if (fieldToCheck == 'url')
	{
		if (fieldValue.substring(0, 4) != 'www.')
		{
			fieldValue = 'www.' + fieldValue;
		}
	}
			
	// Open a new backend connection
	reqFieldCheck = getXmlHttpObject();
	
	var date = new Date();
	var timestamp = date.getTime();
	
	
	var url = adminPath + '/modules/' + moduleName + '/checkValue.php?time=' + timestamp + '&subsite_id=' + subsiteId + '&field_name=' + fieldToCheck + '&field_value=' + encodeURIComponent(fieldValue);

	reqFieldCheck.onreadystatechange=function()
	{
		fieldCheckResponseReceived(fieldToCheck);
	}
	reqFieldCheck.open("GET",url,true);
	reqFieldCheck.send(null);
}
function confirmMessageDelete(senderName, id, adminPath, module, siteId)
{
	var startPage = getUrlParameter('paging_spage');
	if (confirm("Are you sure you want to delete the message from '" + senderName + "'?"))
	{
		targetLocation = adminPath + '?module=' + module + '&task=deleteMessageSubmit&objectid=' + id + '&subsiteid=' + siteId + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}

function confirmSubsiteDelete(subsiteName, id, adminPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you want to delete the sub-site: '" + subsiteName + "'?"))
	{
		targetLocation = adminPath + '?module=' + module + '&task=deleteSubmit&objectid=' + id + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}
/*
function fieldCheckResponseReceived(fieldToCheck)
{
	if (reqFieldCheck.readyState == 4 || reqFieldCheck.readyState == "complete")
	{
		if (reqFieldCheck.status == 200)
		{
			// Possible responses are:
			//
			// 0 - No response, so display no error
			// 1 - Available
			// 2 - Not available
			var responseValue = reqFieldCheck.responseText;
			var theField = document.getElementById(fieldToCheck+'_error');
			
			switch (responseValue)
			{
				case 2:
				case '2':
					theField.className = 'error';
					theField.innerHTML = 'Not Available';
					break;
				case 1:
				case '1':
					theField.className = 'notice';
					theField.innerHTML = 'Available';
					break;
				case 0:
				case '0':
				default:
					theField.className = 'normal';
					theField.innerHTML = responseValue;
					break;
			}
		}
		else if (reqFieldCheck.status == 400)
		{
			alert("The request script is missing. Please contact an administrator.");
			return false;
		}
		// Any other response will be simply ignored, and the user will not receive the benefit of 
		// value checking.
	}
}*/
function loadProducts(subsiteId, adminPath, moduleName)
{
	if (confirm("Do you wish to copy all approved products from the master database to the subsite database?"))
	{
		var url = adminPath + '/modules/' + moduleName + '/modalLoadProducts.php?subsiteid=' + subsiteId;
		showDiv(url);
	}
}
function removeKeyword()
{
	var keywordSelect = document.getElementById('edit_keywords[]');
	var deleteCounter = 0;
	
	for (var i=keywordSelect.length-1; i>=0; i--)
	{
		if (keywordSelect.options[i].selected)
		{
			keywordSelect.remove(i);
			deleteCounter++;
		}
	}
	
	if (!deleteCounter)
	{
		if (keywordSelect.length)
		{
			alert("Please select a keyword to delete.");
		}
		else
		{
			alert("There are no keywords to delete.");
		}
	}
}
function selectAllProducts(numberOfProducts)
{
	for (var i=numberOfProducts; i>=1; i--)
	{
		var checkField = document.getElementById('product_checkbox_'+i);
		if (is_object(checkField))
		{
			checkField.checked = true;
		}
	}
}
function showEditDiv(subsiteId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&objectid=' + subsiteId;
	showDiv(url, null, null, 'general');
}
function showNewDiv(adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module;
	showDiv(url, null, null, 'general');
}
function validateForm_subsites(serverPath, module)
{
	// Verify that the required form fields have been completed
	var isValid = true;
	var output = '';
	var fieldToGetFocus = ''
	
	if ('' == document.getElementById('edit_name').value)
	{
		output += "A name is required for this sub-site.\n";
		fieldToGetFocus = 'edit_name';
		isValid = false;
	}
	if ('' == document.getElementById('edit_url').value)
	{
		output += "A URL is required for this sub-site.\n";
		if ('' == fieldToGetFocus) // If no previous error
		{
			fieldToGetFocus = 'edit_url';
		}
		isValid = false;
	}
	if ('' == document.getElementById('edit_dba_name').value)
	{
		output += "An 'A' database name is required for this sub-site.\n";
		if ('' == fieldToGetFocus) // If no previous error
		{
			fieldToGetFocus = 'edit_dba_name';
		}
		isValid = false;
	}
	if ('' == document.getElementById('edit_dbb_name').value)
	{
		output += "A 'B' database name is required for this sub-site.\n";
		if ('' == fieldToGetFocus) // If no previous error
		{
			fieldToGetFocus = 'edit_dbb_name';
		}
		isValid = false;
	}
	
	// Make sure the database names are different
	if (document.getElementById('edit_dba_name').value == document.getElementById('edit_dbb_name').value)
	{
		output += "The database names must be different.\n";
		if ('' == fieldToGetFocus) // If no previous error
		{
			fieldToGetFocus  = 'edit_dbb_name';
		}
		isValid = false;
	}
	
	// Select all keyword entries
	var keywordSelect = document.getElementById('edit_keywords[]');
	for (var i=0; i<keywordSelect.options.length; i++)
	{
		keywordSelect.options[i].selected = true;
	}
	
	if (isValid == false)
	{		
		alert(output);
		if ('' != fieldToGetFocus)
		{
			document.getElementById(fieldToGetFocus).focus();
		}
		return false;
	}
	else
	{
		return true;
	}
}