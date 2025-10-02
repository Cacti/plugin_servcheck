#!/bin/sh


# Example - How to use external script and variables

/bin/echo $SERVCHECK_TEST_NAME >> /usr/local/share/cacti/plugins/servcheck/file.txt
/bin/echo $SERVCHECK_EXTERNAL_ID >> /usr/local/share/cacti/plugins/servcheck/file.txt
/bin/echo $SERVCHECK_TEST_TYPE >> /usr/local/share/cacti/plugins/servcheck/file.txt


echo OK
return 0;