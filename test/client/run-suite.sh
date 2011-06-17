#!/bin/bash
SELENIUM_SERVER=../selenium/selenium-server-standalone-2.0rc2.jar

echo Launching Selenium Server...
java -jar $SELENIUM_SERVER >log/selenium.log &

echo Bootstrapping...
php ../server/cleanup.php >>log/bootstrap.log
php ../server/settings.php >log/bootstrap.log
php ../server/submit.php >>log/bootstrap.log

echo -n Waiting for Selenium to start (this can take over a minute)
while :; do
  echo -n .
  sleep 1
  tail -1 log/selenium.log
  if (tail -1 log/selenium.log | grep -q 'Started org.openqa.jetty.jetty.Server'); then
    break
  fi
done

phpunit --verbose .

echo Cleaning up...
php ../server/cleanup.php >>log/bootstrap.log
kill `ps -ef | grep "selenium-server.*$" | awk '{print $2}'`
echo Done.
