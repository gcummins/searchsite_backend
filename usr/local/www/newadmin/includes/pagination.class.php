<?php

class Pagination
{
	public $rows = 0;
	public $pages = 0;
	public $rpp = 0;	// Rows Per Page
	public $spage = 0;	// Start Page
	public $startRecord; // Start Record, used after an insert statement
	public $orderby = null;
	public $extraUrlParameters = '';
	public $linkbar_linksbefore = 0;
	public $linkbar_linksafter = 0;
	public $module = null;	// Holds the module that is currently be used. This module will be included
							// when the linkbar URLs are constructed
	private $arrUrlParameters = array('rpp', 'orderby');	// This array contains the variables that are passed
															// with each url. assembleParameters() sets these
															// values and assembles the appropriate URL string.
	
	function Pagination($iModule, $iRows)
	{
		$this->module = intval($iModule);
		
		$this->rows = $iRows;
		
		$this->setRowsPerPage();
		$this->setOrderBy();
		$this->setPages();
		$this->setStartRecord();
		$this->setStartPage();
	}
	
	function getLimitString()
	{
		return "LIMIT " . $this->getStartRecord() . ", " . $this->rpp;
	}
	
	function setOrderBy()
	{
		if (isset($_REQUEST['paging_orderby']))
		{
			$this->orderby = mysql_real_escape_string($_REQUEST['paging_orderby']);
		}
	}
	
	function setRowsPerPage($iRowsPerPage=false)
	{
		if (false !== $iRowsPerPage)
		{
			$this->rpp = intval($iRowsPerPage);
		}
		else
		{
			if (isset($_REQUEST['paging_rpp']) && intval($_REQUEST['paging_rpp']) > 0)
			{
				$this->rpp = (int)$_REQUEST['paging_rpp'];
			}
			elseif (defined('ADMINPANEL_MAX_TABLE_ROWS'))
			{
				$this->rpp = ADMINPANEL_MAX_TABLE_ROWS;
			}
			else
			{
				// If all else fails, set a reasonable default
				$this->rpp = 10;
			}
		}
	}
	
	function setStartRecord()
	{
		if (isset($_REQUEST['paging_startrec']) && (int)$_REQUEST['paging_startrec'] > 0)
		{
			if ((int)$_REQUEST['paging_startrec'] > $this->rows)
			{
				$this->startRecord = $this->rows;
			}
			else
			{
				$this->startRecord = (int)$_REQUEST['paging_startrec'];
			}
		}
	}
	
	function setStartPage()
	{
		if (isset($_REQUEST['paging_spage']) && intval($_REQUEST['paging_spage']) > 0)
		{
			if (intval($_REQUEST['paging_spage']) > $this->pages)
			{
				$this->spage =$this->pages;
			}
			else
			{
				$this->spage = (int)$_REQUEST['paging_spage'];
			}
		}
		else
		{
			// If no input is given, start at the first page
			$this->spage = 1;
		}
		$this->setLinkbarRange();
	}
	
	function getStartRecord()
	{
		if ($this->startRecord)
		{
			return $this->startRecord;
		}
		else if ($this->spage)
		{
			return $this->rpp * ($this->spage - 1);
		}
		else
		{
			return 0;
		}
	}
	
	function setPages()
	{
		$this->pages = ceil($this->rows/$this->rpp);
	}
	
	function setLinkbarRange()
	{
		// Determine how many links to show before
		// and after the current page.
		
		// Set the default number of links before the current page
		if (defined('ADMINPANEL_MAX_PAGINATION_LINKS'))
		{
			$this->linkbar_linksbefore = floor(ADMINPANEL_MAX_PAGINATION_LINKS/2);
		}
		else
		{
			$this->linkbar_linksbefore = floor(10/2);
		}
		
		// Set the default number of links before the current page
		if (defined('ADMINPANEL_MAX_PAGINATION_LINKS'))
		{
			$this->linkbar_linksafter = floor(ADMINPANEL_MAX_PAGINATION_LINKS/2);
		}
		else
		{
			$this->linkbar_linksafter = floor(10/2);
		}
		
		// Re-adjust the number of links before the current page if there is
		// not room for the default number.
		if ($this->spage - $this->linkbar_linksbefore < 1)
		{
			// Add the remainder after the current page
			$this->linkbar_linksafter = $this->linkbar_linksbefore - ($this->spage - 1) + $this->linkbar_linksafter;
			$this->linkbar_linksbefore = $this->spage - 1;
		}
		
		// Re-adjust the number of links after the current page if there is
		// not room for the default number
		while ($this->spage + $this->linkbar_linksafter > $this->pages)
		{
			$this->linkbar_linksafter--;
			if ($this->spage - ($this->linkbar_linksbefore + 1) > 0)
			{
				$this->linkbar_linksbefore++;
			}
		}
	}
	
	private function assembleParameters()
	{
		$output = '';
		foreach ($this->arrUrlParameters as $parameter)
		{
			$output .= "&amp;paging_" . strtolower($parameter) . "=" . $this->{$parameter};
		}
		return $output;
	}
	
	function generateLinkBar()
	{
		$output  = "\n<div class=\"paging_div\">\n";
		$output .= "\t<ul>\n";
		
		// Generate the "first" link
		if ($this->spage > 1)
		{
			$output .= "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH
				. "?module=" . $this->module
				. "&amp;paging_spage=1"
				. $this->assembleParameters()
				. $this->extraUrlParameters
				. "\" class=\"prevnext\">"
				. "&laquo;&nbsp;First</a></li>\n";
		}
		
		// Generate the "previous" link
		if ($this->spage > 2) // Do not generate a Previous link if it is the same as the "first" link
		{
			$output .= "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH
				. "?module=" . $this->module
				. "&amp;paging_spage=" . ($this->spage - 1)
				. $this->assembleParameters()
				. $this->extraUrlParameters
				. "\" class=\"prevnext\">"
				. "&lsaquo;&nbsp;Previous</a></li>\n";
		}
		
		// Generate the "before" links
		if ($this->linkbar_linksbefore)
		{
			$linkBeforeOutput = '';
			for ($i=($this->spage-1); $i>=($this->spage - $this->linkbar_linksbefore); $i--)
			{
				$linkBeforeOutput = "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH
					. "?module=" . $this->module
					. "&amp;paging_spage=" . $i
					. $this->assembleParameters()
					. $this->extraUrlParameters
					. "\">"
					. $i . "</a></li>\n"
					. $linkBeforeOutput;
			}
			$output .= $linkBeforeOutput;
		}
		
		// Generate the current-page link
		$output .= "\t\t<li><a href=\"#\" class=\"currentpage\" onclick=\"return false;\">". $this->spage . "</a></li>\n";
		
		// Generate the "after" links
		for ($i=$this->spage + 1; $i<=$this->spage + $this->linkbar_linksafter; $i++)
		{
			$output .= "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH
				. "?module=" . $this->module
				. "&amp;paging_spage=" . $i
				. $this->assembleParameters()
				. $this->extraUrlParameters
				. "\">"
				. $i . "</a></li>\n";
		}
		
		// Generate the "next" link
		if ($this->spage < $this->pages - 1) // Do not generate a Next link if it is the same as the "last" link
		{
			$output .= "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH
				. "?module=" . $this->module
				. "&amp;paging_spage=" . ($this->spage + 1)
				. $this->assembleParameters()
				. $this->extraUrlParameters
				. "\" class=\"prevnext\">"
				. "Next&nbsp;&rsaquo;</a></li>\n";
		}
		
		// Generate the "last" link
		if ($this->spage < $this->pages)
		{
			$output .= "\t\t<li><a href=\"" . ADMINPANEL_WEB_PATH
				. "?module=" . $this->module
				. "&amp;paging_spage=" . $this->pages
				. $this->assembleParameters()
				. $this->extraUrlParameters
				. "\" class=\"prevnext\">"
				. "Last&nbsp;&raquo;</a></li>\n";
		}
		
		$output .= "\t</ul>\n";
		$output .= "</div>";
		
		return $output;
	}
}

?>