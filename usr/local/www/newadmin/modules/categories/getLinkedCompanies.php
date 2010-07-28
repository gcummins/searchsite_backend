<?php

require_once "../includes/backend_requirements.php";

if (isset($_REQUEST['categoryid']))
{
	// This is an edit request
	$id = (int)$_REQUEST['categoryid'];
	
	// Get a list of stores IDs associated with this in this category
	$query = "SELECT `store` FROM `" . DEALHUNTING_STORECATEGORIES_TABLE . "` WHERE cat=$id;";
	
	$dealhuntingDatabase->query($query, false);
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
		exit();
	}

	$arrLinkedStores = array();
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$arrLinkedStores[$row->store] = null;
	}

	// Get a list of all stores
	$query = "SELECT `id`, `company` FROM `" . DEALHUNTING_COMPANIES_TABLE . "` ORDER BY company ASC;";
	$dealhuntingDatabase->query($query, false);
	if (true === $dealhuntingDatabase->error)
	{
		returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
		exit();
	}
	
	$arrUnlinkedStores = array();
	
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$arrAllStores[$row->id] = $row->company;
		if (!array_key_exists($row->id, $arrLinkedStores))
		{
			$arrUnlinkedStores[$row->id] = $row->company;
		}
		else
		{
			$arrLinkedStores[$row->id] = $row->company;
		}
	}
	//unset ($arrStoresInThisCategory);
	
	$output = '{
';
	$output .= "\t\"linked_companies\": [";
	if (count($arrLinkedStores))
	{
		foreach ($arrLinkedStores as $storeId=>$storeName)
		{
			$output .= "\n\t\t{\"id\": \"" . $storeId . "\", \"name\": \"" . htmlentities($storeName) . "\"},";
		}
	}
	$output .= "\n\t],\n";
	
	$output .= "\t\"unlinked_companies\": [";
	if (count($arrUnlinkedStores))
	{
		foreach ($arrUnlinkedStores as $storeId=>$storeName)
		{
			$output .= "\n\t\t{\"id\": \"" . $storeId . "\", \"name\": \"" . htmlentities($storeName) . "\"},";
		}
	}
	$output .= "\n\t],\n";
	
	$output .= "}";
}
else
{
	returnError(201, "No category ID was provided. Please contact an administrator.", false, null, 'ajax');
	exit();
}

echo $output;
?>