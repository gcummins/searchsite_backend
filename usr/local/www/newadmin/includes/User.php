<?php

class User
{
	public $id = null;
	public $username = '';
	public $firstName = '';
	public $lastName = '';
	public $groupid = -1;
	public $isError = false;
	public $errorMessage = '';

	function User()
	{
		checkLogin($iUsername, $iPassword);
	}
	
	
}

?>