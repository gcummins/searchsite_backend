function confirmDealDelete(dealSubject, dealId, serverPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you want to delete the deal: " + dealSubject + "?"))
	{
		targetLocation		= serverPath + '?module=' + module + '&task=deleteSubmit&dealid=' + dealId + '&paging_spage=' + startPage;
		document.location	= targetLocation;
	}
}

function showNewDiv(adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module;
	showDiv(url);
}
function showEditDiv(dealId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&dealid=' + dealId;
	showDiv(url);
}
function loadSortOrder(theSelect, adminPath, module)
{
	location.href = adminPath + "/?module=" + module + "&paging_orderby="+theSelect.value;
}