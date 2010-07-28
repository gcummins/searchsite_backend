<?php

class Legend
{
	public  $wrapId			= 'legend_wrap';
	public  $contentId		= 'legendContent';
	public  $ulId			= 'legend_ul';
	public  $controlWrapId	= 'legendControlWrap';
	public  $spanClass		= 'legendControlAction';
	public  $spanId			= 'legendControlActionId';
	public  $spanImageDown	= 'images/legend_down.gif';
	private $arrElements	= array();
	public  $isError		= false;
	public  $error			= '';
	
	function Legend($iArrElements)
	{
		if (is_array($iArrElements) || !count($iArrElements))
		{
			$this->arrElements = $iArrElements;
		}
		else
		{
			$this->isError = true;
			$this->error = "A list of elements must be provided to create the legend.";
		}
	}
	
	function create()
	{
		if ($this->isError)
		{
			return false;
		}
		
		// Create the legend structure
		$output  = "\n<div id=\"" . $this->wrapId . "\">";
		$output .= "\n\t<div id=\"" . $this->contentId . "\">";
		$output .= "\n\t\t<ul id=\"" . $this->ulId . "\">";
		foreach ($this->arrElements as $element)
		{
			list($className, $elementText) = $element;
			$output .= "\n\t\t\t<li class=\"legend_$className\">$elementText</li>";
		}
		$output .= "\n\t\t</ul>";
		$output .= "\n\t</div>";
		$output .= "\n\t<div id=\"" . $this->controlWrapId . "\">";
		$output .= "\n\t\t<span class=\"". $this->spanClass . "\" id=\"" . $this->spanId . "\"><img src=\"" . $this->spanImageDown . "\" alt=\"Open close control\" /></span>";
		$output .= "\n\t</div>";
		$output .= "\n</div>";
		
		return $output;
	}
}

?>