<?php

class FeedFieldMapStat
{
	public $statsTableName = 'stats_linksharemonitor';
	public $successfulFTPLoginOne = false;
	public $successfulFTPLoginTwo = false;
	public $filesInFilelist = 0;
	public $filesToRetrieve = 0;
	public $failedDownloads = 0;
	public $error = false;
	public $arrErrorMessages = array();
	
	function getWriteQuery($iStatsTableName=null)
	{
		if ($iStatsTableName != null)
		{
			$this->statsTableName = $iStatsTableName;
		}
		
		if (!$this->verify())
		{
			$arrErrorMessages[] = "Parameters are missing or unset";
			$this->error = true;
			return false;
		}
		else
		{
			// Create the insert query
			return "INSERT INTO `" . $this->statsTableName . "` ("
				. " `time`, `successful_ftp_login_one`, `successful_ftp_login_two`,"
				. " `files_in_filelist`, `files_to_retrieve`, `failed_downloads`"
				. ") VALUES ("
				. "'" . date('Y-m-d H:i:s') . "', {$this->successfulFTPLoginOne},"
				. " {$this->successfulFTPLoginTwo}, {$this->filesInFilelist},"
				. " {$this->filesToRetrieve}, {$this->failedDownloads}"
				. ");";
		}
	}
	
	function verify()
	{
		// Ensure that each of the properties is set with valid values
		$valid = true;
		
		// The successfulFTPLoginXXX variables should contain either 'true' or 'false'
		if ($this->successfulFTPLoginOne !== true  && $this->successfulFTPLoginOne !== false)
		{
			$valid = false;
		}
		if ($this->successfulFTPLoginTwo !== true  && $this->successfulFTPLoginTwo !== false)
		{
			$valid = false;
		}
		
		// The following variables must be non-negative integers
		//   filesInFilelist
		//   filesToRetrieve
		//   failedDownloads
		if (!is_integer($this->filesInFilelist) || $this->filesInFileList < 0)
		{
			$valid = false;
		}
		if (!is_integer($this->filesToRetrieve) || $this->filesToRetrieve < 0)
		{
			$valid = false;
		}
		if (!is_integer($this->failedDownloads) || $this->failedDownloads < 0)
		{
			$valid = false;
		}
		return false;
	}
}

?>