# servcheck

## Cacti Service checker Plugin

This is Cacti's Services monitoring plugin. Based on Webseer plugin.
This plugin allows you to add service monitoring to Cacti. 
You simply add service check, allow service specific test (like certificate test)
and you can add the expected response. Servcheck periodically run test 
and notify if a service check fails. The plugin records statistics
about the connection, it's response, and can alert when the
status changes.

This plugin, like many others, integrates with Cacti's Maintenance or 'maint'
plugin so you can setup maintenance schedules so that known times when a service
is going to be down can be configured so that escallation does not needlessly
take place during maintenance periods.

## Installation

To install the servcheck plugin, simply copy the plugin_servcheck directory to
Cacti's plugins directory and rename it to simply 'servcheck'. Once you have done
this, goto Cacti's Plugin Management page, Install and Enable the servcheck. Once
this is complete, you can grant users permission to create service checks for
various services.

Go to Management -> Service Checker

You need install libcurl library. For more information about Curl/libcurl 
visit https://www.php.net/manual/en/book.curl.php

## Tests and results
Test can return more results. First result is from curl. There is a lot of  information 
about curl connection. Second result is returned data on which you can perform searches.

Each test has icon for display last result. You can use it for creation  of search string.

Each tests return specific data:
HTTP and HTTPS - returns webpage
SMTP, SMTPS - only connect to SMTP server and displays EHLO/HELO
POP3, POP3S - try to login and display first unread message
IMAP, IMAPS - try to login and display unread messages in inbox
DNS - try to resolve A record and return answer
LDAP and LDAPS - do searching in LDAP
FTP, FTPS - try to login and return directory listing
TFTP - try to download specific file
SCP - try to login and download specific file
SMB, SMBS - try to login and download specific file
MQTT - try to subscribe topic or wait for any message and print result

## Important
Recomendation for tests with download -  please download only small not binary files.

Default Libcurl build doesn't compile all services. You have to compile again for SMB, LDAP, ...

For POP3 and IMAP tests is better insert correct username and password. Without credentials, 
curl will return incorrect result.

SCP is in insecure mode - doesn't check SSH server key!

MQTT is only plaintext. You can specify username and password. MQTT test waits for the first messase from a given topic
or for any message if the topic has not been specified.

Do not test other servers without permission!

Servcheck uses a file of certificates from common root CAs to check certificates. This will work 
for certificates issued by common CAs. If you are using a custom CA 
(for example, in a Microsoft AD environment), the test for that certificate will fail 
because servcheck does not know your CA. You must upload the entire chain 
(CA certificate and intermediate certificates). You then associate these with the test 
where the certificate issued by your CA

## Bugs and Feature Enhancements

Bug and feature enhancements for the servcheck plugin are handled in GitHub. If
you find a first search the Cacti forums for a solution before creating an issue
in GitHub - https://github.com/Cacti/plugin_servcheck

You can find more information on our forum - http://forums.cacti.net/viewtopic.php?t=62934

-----------------------------------------------
Copyright (c) 2004-2024 - The Cacti Group, Inc.

