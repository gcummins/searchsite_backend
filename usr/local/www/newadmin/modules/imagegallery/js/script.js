function gallery_deleteImage(imageName, pageIndex, adminPath, module)
{
	var startPage = getUrlParameter('paging_spage');
	
	var targetLocation = adminPath+"?module="+module+"&task=deleteImage&delImage="+imageName+"&spgmPage="+pageIndex+'&paging_spage='+startPage;
	var output = "Image: "+imageName+"\n";
	
	if (confirm("Are you sure you want to delete:\n\n"+output))
	{
		location.href = targetLocation; 
	}
	else
	{
		return false;
	}
}