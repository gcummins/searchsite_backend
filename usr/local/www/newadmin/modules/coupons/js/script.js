function showEditDiv(couponId, adminPath, moduleName, scriptName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module + '&couponid=' + couponId;
	showDiv(url);
}
function showNewDiv(adminPath, scriptName, moduleName, module)
{
	var url = adminPath + '/modules/' + moduleName + '/modalContents.php?scriptname=' + scriptName + '&module=' + module;
	showDiv(url);
}
function confirmCouponDelete(couponId, adminPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	if (confirm("Are you sure you want do delete this coupon?"))
	{
		var targetLocation = adminPath + '?module=' + module + '&task=deleteSubmit&couponid=' + couponId + '&paging_spage=' + startPage;
		document.location = targetLocation;
	}
}
function validateForm_coupons()
{
	if (document.getElementById('edit_description').value == '')
	{
		alert("A description is required.");
		document.getElementById('edit_description').focus();
		return false;
	}
	return true;
}