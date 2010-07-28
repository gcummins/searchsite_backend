function loadSortOrder(theSelect, adminPath, module)
{
	location.href = adminPath + '/?module=' + module + '&paging_orderby='+theSelect.value;
}

function vendors_toggleStatus(filename, currentStatus, adminPath, moduleName, linkElement)
{
	if (currentStatus == '0')
	{
		vendors_blockCatalog(filename, adminPath, moduleName, linkElement);
	}
	else
	{
		vendors_unblockCatalog(filename, adminPath, moduleName, linkElement);
	}
}

function vendors_blockCatalog(filename, adminPath, moduleName, linkElement)
{
	if (confirm("Are you sure you wish to block the catalog '" + filename + "' from the database?"))
	{
		xmlHttpBlockCatalog = getXmlHttpObject();
		
		var backendURL = adminPath + '/modules/' + moduleName + '/blockcatalog.php?filename=' + filename;

		showAJAXWaitControl('Blocking catalog...');
		xmlHttpBlockCatalog.open("GET",backendURL,false);
		xmlHttpBlockCatalog.send(null);
		hideAJAXWaitControl();
		
		var blockCatalogResponse = xmlHttpBlockCatalog.responseText
		if (blockCatalogResponse == '0')
		{
			//alert("The catalog '" + filename +"' has been marked for deletion. The products will be removed from the front end when the feed mapping process next runs.");
			linkElement.innerHTML = "Blocked";
			linkElement.className = "vendors_status_link_Blocked";
		}
		else
		{
			alert("There was an error while attempting to add the file to the block list. Please contact an administrator or try again later.");
			return false;
		}
	}
	else
	{
		return false;
	}
}
