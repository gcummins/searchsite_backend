<?php

class DatabaseConnection
{
	public $server		= null;
	public $username	= null;
	public $password	= null;
	public $database	= null;
	
	public $link			= null;
	public $queryString		= null;
	public $error			= null;
	public $errorMessage	= null;
	public $queryResult		= null;
	public $affectedRows	= 0;
	
	private $exitOnError	= true;
	
	public function DatabaseConnection($iServer, $iUsername, $iPassword, $iDatabase)
	{
		$this->server	= $iServer;
		$this->username	= $iUsername;
		$this->password	= $iPassword;
		$this->database	= $iDatabase;
		
		$this->connect();
	}
	
	public function __destruct()
	{
		mysql_close($this->link);
	}
	
	public function arrays()
	{
		// Return the results as an array of arrays
		$arrResults = array();
		
		while ($row = mysql_fetch_array($this->queryResult))
		{
			$arrResults[] = $row;
		}
		
		// Reset the pointer to the first record;
		$this->resetPointer();
		
		return $arrResults;
	}
	
	private function clearError()
	{
		$this->error = false;
		$this->errorMessage = null;
	}
	
	private function connect()
	{
		if (false !== ($this->link = mysql_connect($this->server, $this->username, $this->password, true)))
		{
			// Connection to the server was successful
			if (false === mysql_select_db($this->database, $this->link))
			{
				// Selection of the database failed.
				$this->error = true;
				$this->throwError(mysql_error($this->link) . " Failed while selecting database '" . $this->database . "'");
			}
		}
		else
		{
			// Connection to the server was unsuccessful
			$this->throwError(mysql_error($this->link) . " Failed while connecting to server.");
		}
		return true;
	}
	
	public function escape_string($string)
	{
		return mysql_real_escape_string($string, $this->link);
	}
	
	public function firstArray()
	{
		// Return the first record as an array
		$record = mysql_fetch_array($this->queryResult);
		$this->resetPointer();
		return $record;
	}

	public function firstField()
	{
		// Return the first field of the first record
		$record = mysql_fetch_array($this->queryResult);
		$this->resetPointer();
		return $record[0];
	}
	
	public function firstObject()
	{
		// Return the first record as an object
		$record = mysql_fetch_object($this->queryResult);
		if (!is_object($record))
		{
			$this->throwError("\$record is not an object.");
		}
		
		$this->resetPointer();
		return $record;
	}
	
	public function firstRecord($type="object")
	{
		// A generic function to return one record. The user can choose to receive the response
		// as an "object" or an "array."
		switch ($type)
		{
			case "array":
				return $this->firstArray();
				break;
			case "object":
			default:
				return $this->firstObject();
				break;
		}
	}
	
	public function insert_id()
	{
		return mysql_insert_id($this->link);
	}
	
	public function objects()
	{
		// Return the results as an array of objects
		$arrResults = array();
		
		while ($row = mysql_fetch_object($this->queryResult))
		{
			$arrResults[] = $row;
		}
		
		// Reset the pointer to the first record;
		$this->resetPointer();
		
		return $arrResults;
	}
	
	public function ping($message=null)
	{
		if (false === mysql_ping($this->link))
		{
			// Attempt to reconnect
			mysql_close($this->link);
			if (!$this->connect())
			{
				logger("The attempt to reconnect to the server failed.", LEVEL_MINUTIA);
				if ($message == null)
				{
					$message = "Lost connection to the MySQL server.";
				}
				$this->throwError($message);
			}
		}
		else
		{
			return true;
		}
	}
	
	public function query($iQueryString=null, $exitOnError=true, $logMessage=true)
	{
		// Allow the last query to be re-run without explicitly stating it
		if ($iQueryString != null)
		{
			$this->queryString = $iQueryString;
		}
		else if (empty($this->queryString))
		{
			$this->throwError("No query was provided.");
		}
		
		// Provide debug-level logging of each query, if the proper facilities are available
		if (FALSE && function_exists('logger') && defined('LOG_LEVEL') && defined('LEVEL_DEBUG') && LOG_LEVEL == LEVEL_DEBUG && $logMessage)
		{
			logger("MYSQL QUERY: " . $this->queryString, LEVEL_DEBUG);
		}
		
		if (false === ($this->queryResult = mysql_query($this->queryString, $this->link)))
		{
			// Override the default behavior if a second parameter was provided to this function
			$this->exitOnError = $exitOnError;
			
			$this->throwError();
			
			// Reset the default behavior
			$this->exitOnError = true;
		}
		
		$this->affectedRows = mysql_affected_rows($this->link);

	}
	
	private function resetPointer($record=0)
	{
		if ($this->rowCount())
		{
			mysql_data_seek($this->queryResult, $record);
		}
	}
	
	public function rowCount()
	{
		return mysql_num_rows($this->queryResult);
	}
	
	private function throwError($message=null)
	{
		$this->error = true;
		if ($message == null)
		{
			$this->errorMessage = mysql_error($this->link);
		}
		else
		{
			$this->errorMessage = $message;
		}
		
		$message = "MySQL Error: " . $this->errorMessage;
		
		// Add a period to the message if it does not already have one
		if (substr($message, -1) != '.')
		{
			$message .= '.';
		}
		
		// Show the last query
		if (!empty($this->queryString))
		{
			$message .= " Last Query: \"" . $this->queryString . "\"";
		}
		
		logger($message, LEVEL_DATABASE_OPERATION);
		
		if ($this->exitOnError)
		{
			exit(1);
		}
	}
}

?>
