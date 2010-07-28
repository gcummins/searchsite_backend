<?php

require_once "../includes/backend_requirements.php";

$query = "SELECT `affiliate_type_id`, `label`FROM `affiliate_type` ORDER BY `label` ASC;";
$dealhuntingDatabase->query($query, false);

if (true === $dealhuntingDatabase->error)
{
	returnError(902, $query, true, $dealhuntingDatabase, 'ajax');
	exit();
}

$output = '{"affiliates":[';

if ($dealhuntingDatabase->rowCount() > 0)
{
	foreach ($dealhuntingDatabase->objects() as $row)
	{
		$output .= '{"affiliate_type_id":' . $row->affiliate_type_id . ', "label":"' . $row->label . '"},';
	}
	$output = substr($output, 0, -1);
	$output .= ']}';
}
else
{
	$output .= 'null]}';
}
echo $output;
?>