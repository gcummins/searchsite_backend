function permissions_toggleSub(theCheckbox, theElement)
{
	try
	{
		if (theCheckbox.checked)
		{
			theElement.className = 'menu_permissions';
		}
		else
		{
			theElement.className = 'menu_permissions hidden';
			for (var i=0; i<theElement.childNodes.length; i++)
			{
				if (theElement.childNodes[i].type == 'checkbox')
				{
					theElement.childNodes[i].checked = false;
				}
				else
				{
					for (var j=0; j<theElement.childNodes[i].childNodes.length; j++)
					{
						if (theElement.childNodes[i].childNodes[j].type == 'checkbox')
						{
							theElement.childNodes[i].childNodes[j].checked = false;
						}
					}
				}
			}
		}
	}
	catch (e)
	{
		theElement.className = 'menu_permissions hidden';
	}
}
function validateForm_permissions(module)
{
	// There is currently nothing to validate. Just return true.
	return true;
}
function reloadPage(modifyValue, modifyType, adminPath, module)
{
	if (modifyValue == '')
	{
		return false;
	}
	if (modifyType == 'group')
	{
		var url = adminPath + "/?module=" + module + "&select_group=" + modifyValue;
	}
	else
	{
		var url = adminPath + "/?module=" + module+ "&select_user=" + modifyValue;
	}
	location.href = url;
	//location.href = adminPath + "/?module=" + module + "&modifyType=" + modifyType + "&modify=" + modifyValue;
}