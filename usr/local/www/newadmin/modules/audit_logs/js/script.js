function getCSVCheck()
{
	// Get the value of the "Create CSV Document" checkbox, if it exists
	if (null == document.getElementById('createcsv'))
	{
		return false;
	}
	else
	{
		return document.getElementById('createcsv').checked;
	}
}
function openTask(taskName, adminPath, module)
{
	location.href=adminPath + "/?module=" + module + "&task="+taskName;
}
function loadLogEntriesByUser(userId, adminPath, moduleName)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_logentries.php?objectid=' + userId + '&objecttype=user';
	loadLogEntries(url, document.getElementById('audit_log_entries'));
}
function loadLogEntriesByModule(moduleId, adminPath, moduleName)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_logentries.php?objectid=' + moduleId + '&objecttype=module';
	loadLogEntries(url, document.getElementById('audit_log_entries'));
}
function getRadioValue(radioObj)
{
	if(!radioObj)
	{
		return "";
	}
	var radioLength = radioObj.length;
	if(radioLength == undefined)
	{
		if(radioObj.checked)
		{
			return radioObj.value;
		}
		else
		{
			return "";
		}
	}
	for(var i = 0; i < radioLength; i++)
	{
		if(radioObj[i].checked)
		{
			return radioObj[i].value;
		}
	}
	return "";
}

function loadLogEntriesBySearch(adminPath, moduleName, maintenance)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_logentries.php?objecttype=search';
	
	if (maintenance)
	{
		// Hide the "delete" button
		document.getElementById('delete_entries').style.display = "none";
	}
	
	// Gather the search parameters
	
	// Search Term
	url += '&searchterm='+escape(document.getElementById('search_term').value);
	
	// Users
	var userSearchSelect = document.getElementById('search_users');
	var userString = '';
	for (var i=0; i<userSearchSelect.options.length; i++)
	{
		if (userSearchSelect.options[i].selected)
		{
			url += '&searchusers[]='+userSearchSelect.options[i].value;
		}
	}
	
	// Date Range
	if (getRadioValue(document.searchform.search_daterange) == 'range')
	{
		url += '&datetype=range';
		url += '&start_month=' + document.getElementById('start_month').value;
		url += '&start_day=' + document.getElementById('start_day').value;
		url += '&start_year=' + document.getElementById('start_year').value;
		url += '&end_month=' + document.getElementById('end_month').value;
		url += '&end_day=' + document.getElementById('end_day').value;
		url += '&end_year=' + document.getElementById('end_year').value;
	}
	else
	{
		url += '&datetype=all';
	}
	
	// SortBy
	url += '&searchsortby='+getRadioValue(document.searchform.search_sortby);
	
	loadLogEntries(url, document.getElementById('audit_log_entries'), maintenance);
}
function loadLogEntriesByDate(dateType, adminPath, moduleName, linkType)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_logentries.php?';
	if (dateType == 'range')
	{
		url += '&objecttype=daterange';
		url += '&start_month=' + document.getElementById('start_month').value;
		url += '&start_day=' + document.getElementById('start_day').value;
		url += '&start_year=' + document.getElementById('start_year').value;
		url += '&end_month=' + document.getElementById('end_month').value;
		url += '&end_day=' + document.getElementById('end_day').value;
		url += '&end_year=' + document.getElementById('end_year').value;
	}
	else
	{
		// User clicked a fixed-range link
		url += '&objecttype=fixedrange';
		if (linkType != null)
		{
			url += '&linktype='+linkType;
		}
		else
		{
			url += '&linktype=today';
		}
	}
	loadLogEntries(url, document.getElementById('audit_log_entries'));
}
function loadLogEntryDetail(logId, adminPath, moduleName)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents_logdetail.php?objectid=' + logId + '&requesttype=byuser';
	loadLogEntriesDetail(url, document.getElementById('audit_log_detail'));
}
function loadLogEntriesDetail(url, outputLocation)
{
	loadLogEntries(url, outputLocation, false, true);
}
function loadLogEntries(url, outputLocation, maintenance, skipRecordCount)
{
	// Empty the output div
	betterInnerHtml(outputLocation, '', true);
	
	// Empty the log detail div
	betterInnerHtml(document.getElementById('audit_log_detail'), '', true);
	
	// Create the timestamp to ensure we do not receive a cached copy
	var date = new Date();
	var timestamp = date.getTime();
	
	// To avoid caching of the backend script
	url = url + '&time=' + timestamp;

	// Determine if the user requested a CSV document.
	if (getCSVCheck())
	{
		// Notify the backend that a CSV document is required.
		url = url + '&getcsv=' + getCSVCheck();
		
		// redirect to this location to prompt the user to download the file
		location.href=url;
	}
	else
	{	
		// First, get a count of the results that will be returned
		if (!skipRecordCount)
		{
			var countUrl = url + '&getcount';
			xmlCountLogEntries = getXmlHttpObject();
			xmlCountLogEntries.onreadystatechange=function()
			{
				alert(xmlCountLogEntries.responseText);
			}
			xmlCountLogEntries.open("GET",countUrl,false);
			xmlCountLogEntries.send(null);
			var countResponse = eval('(' + xmlCountLogEntries.responseText + ')');
			
			if (typeof(countResponse.error) == 'object')
			{
				handle_error(countResponse.error);
			}
			
			var loadingMessage = '<div class="loading_image">Loading ' + countResponse.count + ' records...<br /><img src="images/progress_bar.gif" alt="Loading..." /></div>';
		}
		else
		{
			var loadingMessage = '<div class="loading_image">Loading...<br /><img src="images/progress_bar.gif" alt="Loading..." /></div>';
		}
	
		// Next, get the actual results	
		xmlLoadLogEntries = getXmlHttpObject();
		
		xmlLoadLogEntries.onreadystatechange=function()
		{
			betterInnerHtml(outputLocation, loadingMessage, true);
			loadLogResponseReceived(outputLocation, maintenance);
		}
		xmlLoadLogEntries.open("GET",url,true);
		xmlLoadLogEntries.send(null);
	}
}
function loadLogResponseReceived(outputLocation, maintenance)
{
	if (xmlLoadLogEntries.readyState == 4 || xmlLoadLogEntries.readyState == "complete")
	{
		if (xmlLoadLogEntries.status==200)
		{
			// Determine if the is a JSON response containing an error object,
			// or if it is a regular, successful response
			try
			{
				var responseObject = eval('('+xmlLoadLogEntries.responseText+')');
				if (typeof(responseObject.error) == 'object')
				{
					handle_error(responseObject.error);
				}
				else
				{
					return displayResponse(xmlLoadLogEntries.responseText, outputLocation, maintenance);
				}
			}
			catch (err)
			{
				return displayResponse(xmlLoadLogEntries.responseText, outputLocation, maintenance);
			}
		}
		else if (xmlLoadLogEntries.status==404)
		{
			alert("The request script is missing. Please contact an administrator.");
			return false;
		}
		else
		{
			alert("The request failed with HTTP response code " + xmlLoadLogEntries.status + ". Please contact an administrator.");
			return false;
		}
	}
}
function displayResponse(responseText, outputLocation, maintenance)
{
	var tempnewdiv = document.createElement('div');
	tempnewdiv.style.display = "none";
	tempnewdiv.setAttribute("id", outputLocation.id+"temp");
	tempnewdiv.innerHTML = xmlLoadLogEntries.responseText;
	// Clear anything that is already in the outputLocation
	betterInnerHtml(outputLocation, '', true);
	outputLocation.appendChild(tempnewdiv);
	
	// If this is a maintenance request, display the "Delete" link
	if (maintenance)
	{
		var tableContainer = document.getElementById('audit_log_entriestemp');
		var theTableBody = tableContainer.childNodes[0].childNodes[0];
		if (theTableBody.childNodes.length > 1) 
		{
			document.getElementById('delete_entries').style.display = "block";
		}
	}
	
	// Fade in the results. This fade is required to make a visual impression
	// when the data is updated.
	$("#"+outputLocation.id+"temp").fadeIn("fast");
	
	// Scroll the div to the top
	outputLocation.scrollTop = 0;
	
	return true;
}
function toggleDateRangeDisplay(direction)
{
	if (direction == 'off')
	{
		document.getElementById('start_month').disabled = true;
		document.getElementById('start_day').disabled = true;
		document.getElementById('start_year').disabled = true;
		document.getElementById('end_month').disabled = true;
		document.getElementById('end_day').disabled = true;
		document.getElementById('end_year').disabled = true;
	}
	else
	{
		
		document.getElementById('start_month').disabled = false;
		document.getElementById('start_day').disabled = false;
		document.getElementById('start_year').disabled = false;
		document.getElementById('end_month').disabled = false;
		document.getElementById('end_day').disabled = false;
		document.getElementById('end_year').disabled = false;
	}
}
function deleteLogEntries()
{
	var tableContainer = document.getElementById('audit_log_entriestemp');
	var theTableBody = tableContainer.childNodes[0].childNodes[0];

	var idString = '';
	
	if (theTableBody.childNodes.length == 2)
	{
		var confirmMessage = "Are you sure you wish to delete this record?";
	}
	else
	{
		var recordCount = theTableBody.childNodes.length-1;
		var confirmMessage = "Are you sure you wish to delete these " + recordCount + " records?";
	}
	
	if (theTableBody.childNodes.length > 1)
	{
		if (confirm(confirmMessage))
		{ 
			for (var i=1; i<theTableBody.childNodes.length; i++)
			{
				var onclickEvent = ''+theTableBody.childNodes[i].onclick;
				
				// Parse the onclick string to retrieve the id of the log entry
				var startPosition = onclickEvent.indexOf('loadLogEntryDetail(')+19;
				var endPosition = onclickEvent.indexOf(',');
		
				idString += '&arrIds[]='+onclickEvent.substr(startPosition, endPosition-startPosition);
			}
			// Create the timestamp to ensure we do not receive a cached copy
			var date = new Date();
			var timestamp = date.getTime();
			
			// Send a backend request to delete the ids in 'idString'
			url = adminPath + '/modules/' + moduleName + '/deleteLogEntries.php?time=' + timestamp + idString;
			
			xmlDeleteLogEntries = getXmlHttpObject();
			
			xmlDeleteLogEntries.onreadystatechange=function()
			{
				showAJAXWaitControl("Deleting records...");
				deleteRecordsResponseReceived();
				hideAJAXWaitControl();
			}
			xmlDeleteLogEntries.open("GET",url,true);
			xmlDeleteLogEntries.send(null);
		}
	}
	//alert(idString);
}
function deleteRecordsResponseReceived()
{
	outputLocation = document.getElementById('audit_log_entries');
	
	// Clear anything in the output location
	betterInnerHTML(outputLocation, '', true);
	
	// Add a result message
	betterInnerHTML(outputLocation, 'The selected records have been deleted.');
	
}