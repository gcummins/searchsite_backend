function loadSortOrder(theSelect, serverPath, module)
{
	location.href = serverPath + '/?module=' + module + '&paging_orderby='+theSelect.value;
}
function showEditDiv(redirection_id, adminPath, scriptName, module, moduleName)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&redirectionid=' + redirection_id;
	redirectionShowDiv(url);
}
function showNewDiv(adminPath, scriptName, module, moduleName)
{
	var url = adminPath+'/modules/' + moduleName + '/modalContents.php?scriptname='+scriptName+'&module='+module;
	redirectionShowDiv(url);
}
function redirectionShowDiv(url)
{
	setModalPosition();
	
	xmlHttpModal = getXmlHttpObject();
	
	var date = new Date();
	var timestamp = date.getTime();
	
	url = url + '&time='+timestamp;
	
	xmlHttpModal.onreadystatechange=redirectionModalResponseReceived;
	xmlHttpModal.open("GET",url,true);
	xmlHttpModal.send(null);
}

function redirectionModalResponseReceived()
{
	if (xmlHttpModal.readyState == 4 || xmlHttpModal.readyState == "complete")
	{
		var tempnewdiv = document.createElement('div');
		tempnewdiv.className = 'js_holder_div';
		tempnewdiv.innerHTML = xmlHttpModal.responseText;
		var container = document.getElementById('edit_div');

		// Clear anything that is already in 'edit_div'
		betterInnerHtml(container, '', true);
		container.appendChild(tempnewdiv);

		document.getElementById('modalContainer').style.display = "block";
		document.getElementById('redirection_description').focus();

		return true;
	}
}
function showDeleteConfirmation(redirection_id, redirection_description, serverPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	message = 'Are you sure you wish to delete the following redirection?\n\n';
	message += 'ID: '+redirection_id+'\n';
	message += 'Description: '+redirection_description;
	if(confirm(message))
	{
		targetLocation = serverPath + '?module=' + module + '&task=deleteRedir&redirectionid=' + redirection_id + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}