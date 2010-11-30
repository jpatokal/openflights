#!/bin/bash
echo Launching Selenium RC...
java -jar ../selenium/selenium-server-1.0.3/selenium-server.jar >log/selenium.log &

echo Bootstrapping...
php ../server/cleanup.php >>log/bootstrap.log
php ../server/settings.php >log/bootstrap.log
php ../server/submit.php >>log/bootstrap.log

echo -n Waiting for Selenium to start
for i in 1 2 3 4 5; do echo -n .; sleep 1; done
echo
phpunit --verbose .

echo Cleaning up...
php ../server/cleanup.php >>log/bootstrap.log
kill `ps -ef | grep "selenium-server.jar$" | awk '{print $2}'`
echo Done.

