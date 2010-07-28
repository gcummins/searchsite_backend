function confirmCompanyDelete(companyName, companyId, serverPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you want to delete company: " + companyName + "?"))
	{
		targetLocation		= serverPath + '?module=' + module + '&task=deleteSubmit&companyid=' + companyId + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}
function showEditDiv(companyId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?&scriptname=' + scriptName + '&module=' + module + '&companyid=' + companyId;
	saveType = 'saveEdit';
	showDiv(url, null, null, 'general');
}

function showNewDiv(adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module;
	saveType = 'saveNew';
	showDiv(url, null, null, 'general');
}
function validateForm_companies(serverPath, module)
{
	var validSubmission = true;
	var output = '';

	var companyId = null;
	var task = document.getElementById('edit_task').value;
	if (task == 'saveEdit')
	{
		companyId = document.getElementById('edit_company_id');
	}
	
	var arrOutput = new Array();	

	var arrFields = new Array();
	
	/* PARAMETERS:
		 0: required (true or false)
		 1: field id
		 2: display name
	 */
	arrFields[0] = new Array(true, 'edit_company', 'Company');
	arrFields[1] = new Array(false, 'edit_aff_type', 'Affiliate Type');
	arrFields[2] = new Array(false, 'edit_banner', 'Banner');
	arrFields[3] = new Array(false, 'edit_expiration_date', 'Banner Expiration');
	arrFields[4] = new Array(true, 'edit_url', 'URL');	
	arrFields[5] = new Array(false, 'edit_clean_url', 'Clean URL');	
	arrFields[6] = new Array(false, 'edit_joblo_url', 'JoBlo URL');
	arrFields[7] = new Array(false, 'edit_alert', 'No Coupon Alert');
	arrFields[8] = new Array(false, 'edit_usship', 'Ships to the U.S.');
	arrFields[9] = new Array(false, 'edit_canadaship', 'Ships to Canada');
	arrFields[10] = new Array(false, 'edit_ukship', 'Ships to the U.K.');
	
	// Check for required fields that are emtpy
	for (var j=0; j<arrFields.length; j++)
	{
		theField = arrFields[j];
		if (theField[0] == true && document.getElementById(theField[1]).value == '')
		{
			validSubmission = false;
			arrOutput.push(theField[2]);
		}
	}

	if (validSubmission == false)
	{
		output = "The following field";
		
		if (arrOutput.length > 1)
		{
			output += 's are';
		}
		else
		{
			output += ' is';
		}
		
		output += " required:\n\n";
		
		for (var i=0; i<arrOutput.length; i++)
		{
			output += ' - ' + arrOutput[i] + '\n';
		}
		alert(output);
		return false;
	}
	else
	{
		return true;
	}
}
function loadSortOrder(theSelect, adminPath, module)
{
	location.href = adminPath + "/?module=" + module + "&paging_orderby="+theSelect.value;
}