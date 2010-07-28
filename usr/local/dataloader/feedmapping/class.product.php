<?php

class Product
{
	var $ProgramName		= '';
	var $ProgramURL			= '';
	var $LastUpdated		= '';
	var $ProductName		= '';
	var $Keywords			= '';
	var $LongDescription	= '';
	var $InterimDescription	= '';
	var $ShortDescription	= '';
	var $BriefDescription	= '';
	var $SKU				= '';
	var $Manufacturer		= '';
	var $ManufacturerID		= '';
	var $UPC				= '';
	var $ISBN				= '';
	var $Currency			= '';
	var $SalePrice			= '';
	var $Price				= '';
	var $RetailPrice		= '';
	var $FromPrice			= '';
	var $BuyURL				= '';
	var $AddToCartURL		= '';
	var $ImpressionURL		= '';
	var $ImageURL			= '';
	var $Category			= '';
	var $SecondaryCategory	= '';
	var $CategoryID			= '';
	var $CategoryCrumbs		= '';
	var $Author				= '';
	var $Artist				= '';
	var $Title				= '';
	var $Publisher			= '';
	var $Label				= '';
	var $Format				= '';
	var $Special			= '';
	var $PromotionalText	= '';
	var $StartDate			= '';
	var $EndDate			= '';
	var $ShippingCost		= '';

	function getCopyRecordQuery($destinationTable)
	{
		$query = "INSERT INTO `$destinationTable` ("
				."ProgramName, "
				."ProgramURL, "
				."LastUpdated, "
				."ProductName, "
				."Keywords, "
				."LongDescription, "
				."SKU, "
				."Manufacturer, "
				."ManufacturerID, "
				."UPC, "
				."ISBN, "
				."Currency, "
				."SalePrice, "
				."Price, "
				."RetailPrice, "
				."FromPrice, "
				."BuyURL, "
				."ImpressionURL, "
				."ImageURL, "
				."Category, "
				."CategoryID, "
				."CategoryCrumbs, "
				."Author, "
				."Artist, "
				."Title, "
				."Publisher, "
				."Label, "
				."Format, "
				."Special, "
				."PromotionalText, "
				."StartDate, "
				."EndDate) VALUES ("
				."'$this->ProgramName', "
				."'$this->ProgramURL', "
				."'$this->LastUpdated', "
				."'$this->ProductName', "
				."'$this->Keywords', "
				."'$this->LongDescription', "
				."'$this->SKU', "
				."'$this->Manufacturer', "
				."'$this->ManufacturerID', "
				."'$this->UPC', "
				."'$this->ISBN', "
				."'$this->Currency', "
				."'$this->SalePrice', "
				."'$this->Price', "
				."'$this->RetailPrice', "
				."'$this->FromPrice', "
				."'$this->BuyURL', "
				."'$this->ImpressionURL', "
				."'$this->ImageURL', "
				."'$this->Category', "
				."'$this->CategoryID', "
				."'$this->CategoryCrumbs', "
				."'$this->Author', "
				."'$this->Artist', "
				."'$this->Title', "
				."'$this->Publisher', "
				."'$this->Label', "
				."'$this->Format', "
				."'$this->Special', "
				."'$this->PromotionalText', "
				."'$this->StartDate', "
				."'$this->EndDate');
				";
		return $query;
	}
	function addSlashes()
	{
		foreach ($this as $memberName=>$member)
		{
			$this->{$memberName} = addslashes($member);
		}
	}
}

?>
