#!/bin/sh
#
# Collect gettext strings from all PHP/JavaScript files and update existing .po files
# GNU xgettext does not recognize JavaScript, but oddly enough C seems to parse it (almost) fine...
# 
xgettext --omit-header --no-location -j -o locale/fi_FI.utf8/LC_MESSAGES/messages.po index.php php/*php html/*php
xgettext --omit-header --no-location -L C -j -o locale/fi_FI.utf8/LC_MESSAGES/messages.po openflights.js js/apsearch.js

xgettext --omit-header --no-location -j -o locale/ja_JP.utf8/LC_MESSAGES/messages.po index.php php/*php html/*php
xgettext --omit-header --no-location -L C -j -o locale/fi_FI.utf8/LC_MESSAGES/messages.po openflights.js js/apsearch.js
