<?php

require_once "../includes/backend_requirements.php";

$module = false;
if (isset($_REQUEST['module']) && !empty($_REQUEST['module']))
{
	$module = (int)$_REQUEST['module'];
}

if (defined('ADMINPANEL_MODULES_ORDERBY'))
{
	switch(ADMINPANEL_MODULES_ORDERBY)
	{
		case 'alpha':
			$orderbyString = 'ORDER BY menu_sections.`order` ASC, modules.`display_name` ASC';
			break;
		case 'db':
		default:
			$orderbyString = 'ORDER BY menu_sections.`order` ASC, modules.`order` ASC';
			break;
	}
}
else
{
	$orderbyString = 'ORDER BY menu_sections.`order` ASC, modules.`order` ASC';
}

$query = "SELECT menu_sections.id, menu_sections.display_name as menuName, modules.id as moduleId, modules.display_name as moduleName FROM `modules`LEFT JOIN menu_sections on menu_sections.id=modules.menu_section $orderbyString;";
$result = mysql_query($query, $adminLink) or die(mysql_error($adminLink));

$arrMenu = array();
while ($row = mysql_fetch_object($result))
{
	$arrMenu[$row->menuName][$row->moduleId] = $row->moduleName;
}
?>
<div id="menu_css">
<?php

echo "<ul id=\"menubuttons_ul\" class=\"menubuttons\">\n";
foreach ($arrMenu as $section => $arrModules)
{
	$formattedMenuName = str_replace(array(' ', '.', '-'), '', $section);
	$formattedMenuName2 = str_replace(array(' ', '.', '-'), array('', '_', ''), strtolower($section));
	if (isPermitted('menu_'.$formattedMenuName2))
	{
		echo "\t<li><a href=\"#\" onclick=\"showMenu_css('$formattedMenuName'); return false;\">$section</a></li>\n";
		echo "\t<ul id=\"menu_$formattedMenuName\">\n";
		foreach ($arrModules as $moduleId=>$module)
		{
			if (isPermitted('view', $moduleId))
			{
				if (isset($_REQUEST['module']) && $_REQUEST['module'] == $moduleId)
				{
					echo "\t\t<li class=\"activeSubmenu\"><a href=\"" . ADMINPANEL_WEB_PATH . "/?module=" . $moduleId . "\">$module</a></li>\n";
					$selectedMenu = $formattedMenuName;
				}
				else
				{
					echo "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH . "/?module=" . $moduleId . "\">$module</a></li>\n";
				}
			}
		}
		echo "\t</ul>\n";
	}
}
echo "</ul>\n";
?>
</div>