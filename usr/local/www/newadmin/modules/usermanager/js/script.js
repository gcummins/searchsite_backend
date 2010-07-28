function changeStatus(userId, currentStatus, adminPath, module)
{
	location.href = adminPath + '/modules/usermanager/changestatus.php?ak=0&uid='+userId+'&status='+currentStatus+'&module=' + module;
}
function confirmUserDelete(username, userId, serverPath, module)
{
	if (confirm("Are you sure you want to delete the user: " + username + "?"))
	{
		targetLocation		= serverPath + '?module=' + module + '&task=deleteSubmit&userid=' + userId;
		document.location	= targetLocation;
	}
}
function loadSortOrder(theSelect, adminPath, module)
{
	location.href = adminPath + "/?module=" + module + "&paging_orderby="+theSelect.value;
}
function showEditDiv(userId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_editUser.php?scriptname=' + scriptName + '&module=' + module + '&userid=' + userId;
	showDiv(url); 
}
function showNewDiv(adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_editUser.php?scriptname=' + scriptName + '&module=' + module;
	showDiv(url);
}
function validateForm_usermanager(serverPath, module)
{
	var output = '';
	var focusField = '';
	
	if (document.getElementById('edit_username').value == '')
	{
		output += 'Please enter a username.\n';
		focusField = 'edit_username';
	}
	
	if (document.getElementById('edit_firstname').value == '')
	{
		output += 'Please enter a first name.\n';
		if (focusField == '')
		{
			focusField = 'edit_firstname';
		}
	}
	
	if (document.getElementById('edit_lastname').value == '')
	{
		output += 'Please enter a last name.\n';
		if (focusField == '')
		{
			focusField = 'edit_lastname';
		}
	}
	
	if (output != '')
	{
		alert(output);
		document.getElementById(focusField).focus();
		return false;
	}
	else
	{
		return true;
	}
}