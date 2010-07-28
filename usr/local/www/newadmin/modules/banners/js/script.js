function loadSortOrder(theSelect, serverPath, module)
{
	location.href = serverPath + '/?module=' + module + '&paging_orderby='+theSelect.value;
}
function showEditDiv(id, adminPath, scriptName, module, moduleName)
{
	var startPage = getUrlParameter('paging_spage');
	
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&banner_id=' + id + '&paging_spage=' + startPage;
	showDiv(url);
}
function showNewDiv(adminPath, scriptName, module, moduleName)
{
	var startPage = getUrlParameter('paging_spage');
	
	var url = adminPath+'/modules/' + moduleName + '/modalContents.php?scriptname='+scriptName+'&module='+module + '&paging_spage=' + startPage;
	showDiv(url);
}
function showDeleteConfirmation(id, name, serverPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	message = 'Are you sure you wish to delete the following banner?\n\n';
	message += 'ID: '+id+'\n';
	message += 'Name: '+name;
	if(confirm(message))
	{
		targetLocation = serverPath + '?module=' + module + '&task=deleteSubmit&banner_id=' + id + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}
function validateForm_banners()
{
	var output = '';
	var focusField = '';
	
	if (isEmpty('banner_name'))
	{	
		output += "A name is required.\n";
		focusField = 'name';
	}
	
	if (isEmpty('banner_link_url'))
	{
		output += "A link URL is required.\n";
		if (focusField == '')
		{
			focusField = 'link_url';
		}
	}
	
	if (isEmpty('banner_image_url'))
	{
		output += "An image URL is required.\n";
		if (focusField == '')
		{
			focusField = 'image_url';
		}
	}
	
	if (output != '')
	{
		alert(output);
		document.getElementById('banner_'+focusField).focus();
		return false;
	}
	else
	{
		return true;
	}
}

function isEmpty(fieldId)
{
	if (document.getElementById(fieldId).value == '')
	{
		return true;
	}
	else
	{
		return false;
	}
}