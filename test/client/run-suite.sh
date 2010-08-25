#!/bin/sh
# Launch Selenium RC
java -jar selenium/selenium-server-1.0.3/selenium-server.jar >log/selenium.log &

# Set up test environment
php ../server/settings.php

# Wait for Selenium to start up, then run tests
sleep 5
phpunit login.php

# Clean up environment
php ../server/cleanup.php

# And then kill Selenium RC
kill `ps -ef | grep "selenium-server.jar$" | awk '{print $2}'`

