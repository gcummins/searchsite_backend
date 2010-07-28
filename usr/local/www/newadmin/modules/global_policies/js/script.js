function toggleDisabled()
{
	// If 'lock_attempts_on' is checked, we must ensure that
	// the following fields are enabled:
	//  lock_time_reset_on
	//  lock_time_reset
	//  lock_minutes_on
	//  lock_minutes
	obj = document.getElementById('lock_attempts_on');
	
	if (obj.checked)
	{
		// Enable the form fields
		document.getElementById('lock_time_reset_on').disabled=false;
		document.getElementById('lock_time_reset').disabled=false;
		document.getElementById('lock_minutes_on').disabled=false;
		document.getElementById('lock_minutes').disabled=false;
	}
	else
	{
		// Disable the form fields
		document.getElementById('lock_time_reset_on').disabled=true;
		document.getElementById('lock_time_reset').disabled=true;
		document.getElementById('lock_minutes_on').disabled=true;
		document.getElementById('lock_minutes').disabled=true;
	}
}