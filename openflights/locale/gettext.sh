#!/bin/sh
#
# Collect gettext strings from all PHP files and update existing .po files
# GNU xgettext does not recognize JavaScript, but oddly enough C seems to parse it (almost) fine...
# 
xgettext --omit-header -j -o locale/fi_FI.utf8/LC_MESSAGES/messages.po php/*php html/*php
xgettext --omit-header -L C -j -o locale/fi_FI.utf8/LC_MESSAGES/messages.po js/apsearch.js

xgettext --omit-header -j -o locale/ja_JP.utf8/LC_MESSAGES/messages.po php/*php html/*php
xgettext --omit-header -L C -j -o locale/fi_FI.utf8/LC_MESSAGES/messages.po js/apsearch.js
