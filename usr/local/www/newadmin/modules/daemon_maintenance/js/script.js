function cancelForm(adminPath, module)
{
	location.href = adminPath + '?module=' + module;
}
function toggleDaemon(daemonid, theAction, adminPath)
{
	globalVarDaemonId = daemonid;
	// We will create an AJAX connection to handle the backend request to enable or disable this daemon
	toggleRequest = getXmlHttpObject();
	
	if (toggleRequest == null)
	{
		alert("Your browser is not compatible with the AJAX functions of this application. Please upgrade your browser to the most current version. If you do not know where to find a current browser, please visit http://www.getfirefox.com .");
		return;
	}
	
	var requestURL = adminPath + '/modules/daemon_maintenance/toggleDaemon.php';
	
	parameters = 'ak=1&daemonid='+daemonid+'&action='+theAction;
	toggleRequest.onreadystatechange = handleDaemonToggle;
	toggleRequest.open('POST', requestURL, true);
	toggleRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	toggleRequest.setRequestHeader("Content-length", parameters.length);
	toggleRequest.setRequestHeader("Connection", "close");
	toggleRequest.send(parameters);
}

function handleDaemonToggle()
{
	if (toggleRequest.readyState==4 || toggleRequest.readyState=="complete")
	{
		if (toggleRequest.status==200)
		{
			toggleRequestResponse = toggleRequest.responseText;
			
			if (toggleRequestResponse == '100')
			{
				// Daemon was deactivated successfully
				alert("Daemonid "+globalVarDaemonId+" was deactivated successfully.");
				
			}
			else if (toggleRequestResponse == '200')
			{
				// Daemon was activated successfully
				alert("Daemonid "+globalVarDaemonId+" was activated successfully.");
			}
			else
			{
				alert("Unable to modify the daemon status. Response code: "+toggleRequestResponse);
			}
		}
		else
		{
			if (toggleRequest.status == 404)
			{
				alert("Request failed because the target script does not exist.");
			}
			else if (toggleRequest.status == 503)
			{
				alert("Request failed because the target script permissions make it inaccessible.");
			}
			else
			{
				alert("Request failed with HTTP Response Code "+toggleRequest.status);
			}
		}
	}
}