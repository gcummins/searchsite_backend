<?php
class pageContentTable
{
	private $title = '';
	private $titlebarClass = 'table_titlebar';
	private $class = 'contentTable';
	private $arrColumnNames = array();
	private $createnewId = 'create_new';
	private $createnewButtonText = '';
	private $createnewButtonAction = '';
	private $addendumHTML = '';
	private $arrDataRows = array();

	private $is_error = false;
	private $error_message = '';
	
	function pageContentTable($iTitle, $iArrColumnNames, $icreatenewButtonText, $icreatenewButtonAction, $iArrDataRows=array(), $iExtraData=null)
	{
		$this->title = $iTitle;
		$this->populateColumnNames($iArrColumnNames);
		$this->createnewButtonText = $icreatenewButtonText;
		$this->createnewButtonAction = $icreatenewButtonAction;
		$this->populateDataRows($iArrDataRows);
		unset($iArrDataRows);
		
		if ($iExtraData)
		{
			$this->addendumHTML = $iExtraData;
			unset($iExtraData);
		}
	}
	
	function error()
	{
		if ($this->error_message)
		{
			return $this->error_message;
		}
		else
		{
			return false;
		}
	}
	
	function populateColumnNames($iArrColumnNames)
	{
		foreach ($iArrColumnNames as $iColumn)
		{
			$this->arrColumnNames[] = $iColumn;
		}
	}
	
	function populateDataRows($iArrDataRows)
	{		
		// First, ensure that the data we received is an array
		if (!is_array($iArrDataRows))
		{
			$this->is_error = true;
			$this->error_message = "The data provided is not a valid array.";
			return false;
		}
		else
		{
			$this->arrDataRows = $iArrDataRows;
			unset($iArrDataRows);
		}
		
		
	}
	function createTable()
	{
		$output  = "<table class=\"" . $this->class . "\">";
		
		$output .= "<tr class=\"create_button_row\">";
		if ($this->createnewButtonText && $this->createnewButtonAction)
		{
			$output .= "<td colspan=\"" . count($this->arrColumnNames) . "\" id=\"". $this->createnewId . "\">";
			$output .= "<input type=\"button\" value=\"". $this->createnewButtonText . "...\" onclick=\"" . $this->createnewButtonAction . "\" />";
			$output .= "</td>";
		}
		else
		{
			$output .= "<td id=\"" . $this->createnewId . "\" class=\"empty_create_button_cell\"></td>";
		}
		$output .= "</tr>";
		$output .= "<tr class=\"" . $this->titlebarClass . "\">";
		$output .= "<td colspan=\"". count($this->arrColumnNames) . "\">" . $this->title . "</td>";
		$output .= "</tr><tr>";
		
		foreach ($this->arrColumnNames as $columnName)
		{
			$output .= "<th>" . $columnName . "</th>";
		}
		$output .= "</tr>";
		
		if (count($this->arrDataRows))
		{		
			foreach ($this->arrDataRows as $arrMetaRow)
			{
				
				$output .= "<tr " . $arrMetaRow[0] . ">";
				foreach ($arrMetaRow[1] as $arrRow)
				{
					$output .= "<td " . $arrRow[0] . ">";
					$output .= "" . $arrRow[1]; 
					$output .= "</td>";
				}
				$output .= "</tr>";
			}
		}
		else
		{
			$output .= "<tr>";
			$output .= "<td colspan=\"" . count($this->arrColumnNames) . "\">This table contains no data</td>";
			$output .= "</tr>";
		}
		if (is_array($this->addendumHTML))
		{
			foreach ($this->addendumHTML as $addendum)
			{
				$output .= "<tr class=\"addendum_tr\">"
					. "<td class=\"addendum_td\" colspan=\"" . count($this->arrColumnNames) . "\">"
					. $addendum
					. "</td>"
					. "</tr>";
			}
		}
		elseif ($this->addendumHTML != '')
		{
			$output .= "<tr class=\"addendum_tr\">"
				. "<td class=\"addendum_td\" colspan=\"" . count($this->arrColumnNames) . "\">"
				. $this->addendumHTML
				. "</td>"
				. "</tr>";
		}
		$output .= "</table>";
		
		return $output;
	}
}
?>