#!/bin/sh
#
# Collect gettext strings from all PHP/JavaScript files and update existing .po files
# GNU xgettext does not recognize JavaScript, but oddly enough C seems to parse it (almost) fine...
# 
PHP="index.php php/*php html/*php"
JS="openflights.js js/alsearch.js js/apsearch.js js/settings.js js/trip.js"

for lang in de_DE fi_FI fr_FR ja_JP lt_LT sv_SE ru_RU; do
  echo $lang
  xgettext --omit-header --no-location --no-wrap -j -o locale/$lang.utf8/LC_MESSAGES/messages.po $PHP
  xgettext --omit-header --no-location  --no-wrap -L C -j -o locale/$lang.utf8/LC_MESSAGES/messages.po $JS
done

xgettext --omit-header --no-location --no-wrap -j -o locale/template.po $PHP
xgettext --omit-header --no-location --no-wrap -L C -j -o locale/template.po $JS
