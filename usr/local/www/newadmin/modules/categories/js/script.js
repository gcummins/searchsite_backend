function showLinkedCompaniesDiv(categoryId, adminPath, moduleName)
{
	// Set the select names
	selectNameLinked	= 'sel_linked_companies';
	selectNameUnlinked	= 'sel_unlinked_companies';

	// Empty the select Lists
	document.getElementById(selectNameLinked).options.length = 0;
	document.getElementById(selectNameUnlinked).options.length = 0;
	
	// Set the modal position
	var modalWidth = 700;
	setModalPosition(modalWidth, 'div_linked_companies_inner_container');
	document.getElementById('div_linked_companies_inner_container').style.width = modalWidth + 'px';
	
	// Display the containing div
	document.getElementById('div_linked_companies_container').style.display = 'block';
	
	showAJAXWaitControl("Loading Link List...");
	
	// Get the JSON data frome the backend
	linkedCompaniesRequest = getXmlHttpObject();
	
	var date = new Date();
	var timestamp = date.getTime();
	
	var url = adminPath + '/modules/' + moduleName + '/getLinkedCompanies.php?time=' + timestamp + '&categoryid=' + categoryId;

	linkedCompaniesRequest.open("GET",url,false);
	linkedCompaniesRequest.send(null);

	objCompanies = eval('(' + linkedCompaniesRequest.responseText + ')');

	populateList(objCompanies.linked_companies, selectNameLinked);

	populateList(objCompanies.unlinked_companies, selectNameUnlinked);

	// Insert the company ID into the appropriate form field
	document.getElementById('categoryid').value = categoryId;
	
	hideAJAXWaitControl();
}
function populateList(arrItems, listId)
{
	// Empty the select
	theSelect = document.getElementById(listId);
	theSelect.options.length = 0;

	// Do not do anything if the list is empty
	try
	{
		arrItems.length;
	}
	catch (e)
	{
		// There are no element in this object
		return;
	}

	// Alphabetize the list items
	arrItems.sort(
		function (a,b)
		{
			var x = a.name.toLowerCase();
			var y = b.name.toLowerCase();
			return ((x < y) ? -1 : ((x > y) ? 1 : 0));
		}
	);
	
	// Add the list items to the select
	for(var i = 0; i<arrItems.length; i++)
	{
		addSelectOption(arrItems[i].id, arrItems[i].name, listId);
	}
}
function linkSelected()
{
	selLinked = document.getElementById(selectNameLinked);
	selUnlinked = document.getElementById(selectNameUnlinked);

	for (var i = selUnlinked.length-1; i>=0; i--)
	{
		if (selUnlinked.options[i].selected)
		{
			var newIndex = objCompanies.linked_companies.length;
			objCompanies.linked_companies[newIndex] = {"id":selUnlinked.options[i].value, "name":selUnlinked.options[i].text};
			for (var j=0; j<objCompanies.unlinked_companies.length; j++)
			{
				if (objCompanies.unlinked_companies[j].id == selUnlinked.options[i].value)
				{
					objCompanies.unlinked_companies.splice(j, 1);
				}
			}
		}
	}
	populateList(objCompanies.linked_companies, selectNameLinked);
	populateList(objCompanies.unlinked_companies, selectNameUnlinked);
}
function unlinkSelected()
{
	selLinked = document.getElementById(selectNameLinked);
	selUnlinked = document.getElementById(selectNameUnlinked);
	
	for (var i = selLinked.length-1; i>=0; i--)
	{
		if (selLinked.options[i].selected)
		{
			var newIndex = objCompanies.unlinked_companies.length;
			objCompanies.unlinked_companies[newIndex] = {"id":selLinked.options[i].value, "name":selLinked.options[i].text};
			for (var j=0; j<objCompanies.linked_companies.length; j++)
			{
				if (objCompanies.linked_companies[j].id == selLinked.options[i].value)
				{
					objCompanies.linked_companies.splice(j, 1);
				}
			}
		}
	}
	populateList(objCompanies.linked_companies, selectNameLinked);
	populateList(objCompanies.unlinked_companies, selectNameUnlinked);
}
function addSelectOption(optionId, optionName, selectId)
{
	var theSelect = document.getElementById(selectId);
	
	newOption = document.createElement('option');
	var ta=document.createElement("textarea");
	ta.innerHTML = optionName.replace(/</g,"&lt;").replace(/>/g,"&gt;");
	optionName = ta.value;
	newOption.text = optionName
	newOption.value = optionId;

	try
	{
		theSelect.add(newOption, null); // Standards-compliant method, but does not work in IE
	}
	catch (e)
	{
		theSelect.add(newOption); // For IE
	}
}
function hideLinkedCompaniesDiv()
{
	document.getElementById('categoryid').value = null;
	document.getElementById('div_linked_companies_container').style.display = 'none';
}
function validateForm_linking()
{
	var validSubmission = false;
	
	// We must select all options or they will not be available to the PHP script when the form is submitted.
	validSubmission = selectAllOptions(selectNameLinked);
	
	return validSubmission;
}
function selectAllOptions(selectId)
{
	var theSelect = document.getElementById(selectId)
	
	if (theSelect.length)
	{
		for (var i=0; i<theSelect.length; i++)
		{
			theSelect.options[i].selected = true;
		}
		return true;
	}
	else
	{
		return confirm("There are no companies linked to this category. Are you sure you wish to continue?");
	}
}
function showEditDiv(categoryId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&categoryid=' + categoryId;
	showDiv(url);
}
function showNewDiv(adminPath, scriptName, module, moduleName)
{	
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module;
	showDiv(url);
}
function confirmCategoryDelete(categoryName, categoryId, serverPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you want to delete category: " + categoryName + "?"))
	{
		targetLocation		= serverPath + '?module=' + module + '&task=deleteSubmit&categoryid=' + categoryId + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}
function validateForm_categories()
{
	// In Progress: To be completed

	// Gather the form fields
	//category_id						= document.getElementById('edit_category_id').value;
	category_name				= document.getElementById('edit_category').value;
	//category_orderby			= document.getElementById('edit_order').value;
	//category_banner				= document.getElementById('edit_banner').value;
	//category_benddate			= document.getElementById('edit_expiration_date').value;	
	category_task					= document.getElementById('task').value;
	
	if (category_task != 'editSubmit' && category_task != 'addSubmit')
	{
		alert("No task was specified, so it is unclear whether this is a new redirection or an edit of an existing redirection. Please contact an administrator.");
		return false;
	}
	
	if (category_name == '')
	{
		alert("A category name is required.");
		return false;
	}	
	
	return true;
}