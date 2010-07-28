#!/bin/sh

# Determine if the linkshare monitor is running

if ps ax | grep -v grep | grep linkshare_monitor.php > /dev/null # The daemon is running
then
 # Nothing is needed
else
 if [ -f /var/run/dataloader/linkshare_monitor.pid ]
 then
   rm /var/run/dataloader/linkshare_monitor.pid # Delete the PID file
 fi
 /usr/local/bin/php /usr/local/dataloader/linkshare_monitor.php # Start the daemon
fi

# Determine if the dataloader is running

if ps ax | grep -v grep | grep dataloader.php > /dev/null  # The daemon is running
then
 # Nothing is needed
else
 if [ -f /var/run/dataloader/dataloader.pid ]
 then
  rm /var/run/dataloader/dataloader.pid # Delete the PID file
 fi
 /usr/local/bin/php /usr/local/dataloader/dataloader.php # Start the daemon
fi

