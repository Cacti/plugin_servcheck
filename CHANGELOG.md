# ChangeLog

--- develop ---

--- 0.4 ---

* feature: Add cpu/memory statistics
* feature: Better process control
* feature: Add return data size stats
* feature: Add log retention
* feature: Command run only for selected tests
* feature: Add configurable test timeout
* feature: Add Servcheck graph template
* issue: Better themes support

--- 0.3 ---

! IMPORTANT - A lot of changes in 0.3. I tried to convert old data.
! The update will create a copy of the MySQL tables in case of errors or data loss.

* feature: Enhance the usage of custom certificate
* feature: Add certificate expiration notification, add this check to all tests with certificates
* feature: Add SFTP, SCP, SSH command test
* feature: Add SSH keys credential method
* feature: Add option to turn off email notifications for individual tests
* feature: Add attempts - try again if test failed
* feature: Better logging and graphs
* feature#9: Add Rest API
* feature#23: Add MQTT and DNS over HTTPS test
* feature: Add ability to bypass resolver for HTTP/HTTPS tests
* feature: Add long duration test notification
* issue: Better result logic and notification
* issue#35: Fix certificate check does not function as expected
* issue#2: Fix graph legend overlap
* issue#31: Fix poller_servcheck.php does not run any test when it runs automatically from cacti poller
* issue: Unable to save poller for service check
* issue#27: Fix all tests are enabled, however, some get performed and some don't
* issue#30: Fix DNS tests
* issue: Better input validation
* issue#42: Fix tests duplicating
* issue#48: Down trigger more than 1 email is not sent


--- 0.2 ---

* issue#17: Remove dependency on thold plugin, notify lists remain if thold is installed
* issue#15: Fix DNS test issue
* issue#19: Rename old thold names, fix incorrect variable
* issue: Fix incorrect result logic
* feature#14: add settings tab, add send email separately option

--- 0.1 ---

* Initial public release: Based on plugin webseer version 3.2

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.

