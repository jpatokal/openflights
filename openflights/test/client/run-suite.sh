#!/bin/bash
SELENIUM_SERVER=../selenium/selenium-server-standalone-2.0rc2.jar

echo Bootstrapping...
php ../server/cleanup.php >>log/bootstrap.log
php ../server/settings.php >log/bootstrap.log
php ../server/submit.php >>log/bootstrap.log

if (ps -ef | grep -q "selenium-server.*jar$"); then
  echo Selenium Server already running:
  ps -ef | grep "selenium-server.*jar$"
else
  echo -n "Launching Selenium Server (this can take over a minute)"
  java -jar $SELENIUM_SERVER >log/selenium.log &
  while :; do
    echo -n .
    sleep 1
    tail -1 log/selenium.log
    if (tail -1 log/selenium.log | grep -q 'Started org.openqa.jetty.jetty.Server'); then
      break
    fi
  done
fi

phpunit --verbose .

echo Cleaning up...
php ../server/cleanup.php >>log/bootstrap.log
echo Done.
