<?php

class SpeedCheck
{
	private $startTime	= null;
	private $endTime	= null;
	private $task		= null;
	
	function SpeedCheck($iTask=null)
	{
		if (!empty($iTask))
		{
			$this->task = $iTask;
		}
		
		// Set the start time now, to obviate the need to manually set it later using start()
		$this->startTime = microtime(true);
	}
	
	function start()
	{
		$this->startTime = microtime(true);
	}
	
	function stop()
	{
		$this->endTime = microtime(true);
	}
	
	function getDuration()
	{
		return $this->endTime - $this->startTime;
	}
	
	function getLogMessage($decimalPlaces=2)
	{
		if (!empty($this->task))
		{
			return "SPEEDCHECK: " . $this->task . " took " . round($this->getDuration(), $decimalPlaces) . " seconds to complete.";
		}
		else
		{
			return "SPEEDCHECK: This task took " . round($this->getDuration(), $decimalPlaces) . " seconds to complete.";
		}
	}
}

?>
