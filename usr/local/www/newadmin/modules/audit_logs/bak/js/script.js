function openTask(taskName, adminPath, module)
{
	location.href=adminPath + "/?module=" + module + "&task="+taskName;
}

function displayEventInformation()
{
	if (eventRequest.readyState==4 || eventRequest.readyState=="complete")
	{
		if (eventRequest.status==200)
		{
			var responseObject = eval('('+eventRequest.responseText+')');
			
			// See if there is an error we need to handle4
			if (typeof(responseObject.error) == 'object')
			{
				handle_error(responseObject.error);
			}
			else
			{
				ouputLocation = document.getElementById('logEntryDiv');

				outputText = '<label>Event:</label>';
				outputText += responseObject.event.task;
				outputText += '<br />';
				outputText += '<label>User:</label>';
				outputText += responseObject.event.username;
				outputText += '<br />';
				outputText += '<label>Date/Time:</label>';
				outputText += responseObject.event.date_time;
				outputText += '<br />';
				outputText += '<label>Note:</label>';
				outputText += responseObject.event.note;
				outputText += '<br />';
				outputText += '<label>Type:</label>';
				outputText += responseObject.event.type;
				outputText += '<br />';
				outputText += '<label>Module:</label>';
				outputText += responseObject.event.module;
				outputText += '<br />';
				outputText += '<label>Script:</label>';
				outputText += responseObject.event.script;
				outputText += '<br />';
				outputText += '<label>Referer:</label>';
				outputText += responseObject.event.referer;
				outputText += '<br />';
				outputText += '<label>IP Address:</label>';
				outputText += responseObject.event.ip;
				outputText += '<br />';
				
				outputLocation.innerHTML = outputText;
			}
		}
		else
		{
			if (userRequest.status == 404)
			{
				alert("Request failed because the target script does not exist.");
			}
			else if (userRequest.status == 503)
			{
				alert("Request failed because the target script permissions make it inaccessible.");
			}
			else
			{
				alert("Request failed with HTTP Response Code: "+xmlHttp.status);
			}
		}
	}
}
function displayUserInformation()
{
	if (userRequest.readyState==4 || userRequest.readyState=="complete")
	{
		if (userRequest.status==200)
		{
			var responseObject = eval('('+userRequest.responseText+')');
			
			// See if there is an error we need to handle
			if (typeof(responseObject.error) == 'object')
			{
				handle_error(responseObject.error);
			}
			else
			{
				ouputLocation = document.getElementById('logEntryDiv');

				outputText = '<label>Username:</label>';
				outputText += responseObject.user.username+'<br />';
				outputText += '<label>UID:</label>';
				outputText += responseObject.user.id+'<br />';
				outputText += '<label>First Name:</label>';
				outputText += responseObject.user.firstName+'<br />';
				outputText += '<label>Last Name:</label>';
				outputText += responseObject.user.lastName+'<br />';
				outputText += '<label>Email:</label>';
				outputText += '<a href="mailto:'+responseObject.user.emailAddress+'">'+responseObject.user.emailAddress+'</a><br />';
				outputText += '<label>Last Login:</label>';
				outputText += responseObject.user.lastLogin+'<br />';
				outputText += '<label>Login Status:</label>';
				if (responseObject.user.loginStatus == '1')
				{
					outputText += 'Logged In<br />';
				}
				else
				{
					outputText += 'Logged Out<br />';
				}
				
				outputLocation.innerHTML = outputText;
			}
		}
		else
		{
			if (userRequest.status == 404)
			{
				alert("Request failed because the target script does not exist.");
			}
			else if (userRequest.status == 503)
			{
				alert("Request failed because the target script permissions make it inaccessible.");
			}
			else
			{
				alert("Request failed with HTTP Response Code: "+xmlHttp.status);
			}
		}
	}
}
/*
This function is apparently unused
function loadUserLog(userID, username, adminPath)
{
	outputLocation = document.getElementById('logEntryDiv');
	
	// We want to load the following information about the user
	// First Name
	// Last Name
	// Email Address
	// Last Login
	// Current login status: 1=logged in, 0=not logged in
	
	// Create a new backend request
	
	userRequest = getXmlHttpObject();
	if (userRequest == null)
	{
		alert("Your browser is not compatible with the AJAX functions of this application. Please upgrade your browser to the most current version. If you do not know where to find a current browser, please visit http://www.getfirefox.com .");
		return;
	}
	
	userRequestUrl = adminPath +'/modules/audit_logs/getUserInformation.php?uid='+userID;
	userRequest.onreadystatechange=displayUserInformation;
	userRequest.open("GET", userRequestUrl, true);
	userRequest.send(null);
}
*/
function loadLogEntry(eventID, adminPath)
{
	outputLocation = document.getElementById('logEntryDiv');
	
	// Create a new backend request
	
	eventRequest = getXmlHttpObject();
	if (eventRequest == null)
	{
		alert("Your browser is not compatible with the AJAX functions of this application. Please upgrade your browser to the most current version. If you do not know where to find a current browser, please visit http://www.getfirefox.com .");
		return;
	}
	
	eventRequestUrl = adminPath + '/modules/audit_logs/getEventInformation.php?eventid='+eventID;
	eventRequest.onreadystatechange=displayEventInformation;
	eventRequest.open("GET", eventRequestUrl, true);
	eventRequest.send(null);
}
function toggleDateFields()
{
	objDateRange = document.getElementById("date_range_specific");
	
	// Gather the fields that we will enable or disable
	fromMonth = document.getElementById("from_month");
	fromDay = document.getElementById("from_day");
	fromYear = document.getElementById("from_year");
	toMonth = document.getElementById("to_month");
	toDay = document.getElementById("to_day");
	toYear = document.getElementById("to_year");
	
	if (objDateRange.checked == true)
	{
		fromMonth.disabled = false;
		fromDay.disabled = false;
		fromYear.disabled = false;
		toMonth.disabled = false;
		toDay.disabled = false;
		toYear.disabled = false;
	}
	else
	{
		fromMonth.disabled = true;
		fromDay.disabled = true;
		fromYear.disabled = true;
		toMonth.disabled = true;
		toDay.disabled = true;
		toYear.disabled = true;
	}
}