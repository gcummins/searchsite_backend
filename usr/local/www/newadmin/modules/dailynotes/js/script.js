function confirmDailynoteDelete(noteDate, noteId, serverPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you want to delete the note for: " + noteDate + "?"))
	{
		targetLocation		= serverPath + '?module=' + module + '&task=deleteSubmit&noteid=' + noteId + '&paging_spage=' + startPage;
		document.location	= targetLocation;
	}
}
function showNewDiv(adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module;
	showDiv(url);
}

function showEditDiv(noteId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&noteid=' + noteId;
	showDiv(url);
}
function validateForm_dailynotes(serverPath, module)
{
	var output = '';
	var focusField = '';
	if (document.getElementById('edit_note').value == '')
	{
		output += 'Please enter a note.\n';
		focusField = 'edit_note';
	}
	if (document.getElementById('edit_showdate').value == '')
	{
		output += 'Please enter a date to show the note.\n';
		if (focusField == '')
		{
			focusField = 'edit_showdate';
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