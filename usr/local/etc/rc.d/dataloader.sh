#!/bin/sh
#
#

# PROVIDE: dataloader
# REQUIRE: LOGIN
# BEFORE: reindexer

#
# Add the following line to /etc/rc.conf to enable dataloader:
#
#dataloader_enable="YES"
#
#

. /etc/rc.subr

name="dataloader"
rcvar=`set_rcvar`

command="/usr/local/bin/php /usr/local/dataloader/dataloader.php"
pidfile="/usr/local/dataloader/run/dataloader.pid"
user="dataloader"

stop_postcmd=stop_post_cmd
stop_post_cmd()
{
   rm -f $pidfile
}

load_rc_config $name
run_rc_command "$1"
