#!/bin/sh
#
# Collect gettext strings from all PHP/JavaScript files and update existing .po files
# GNU xgettext does not recognize JavaScript, but oddly enough C seems to parse it (almost) fine...
# 
PHP="index.php php/*php html/*php"
JS="openflights.js js/alsearch.js js/apsearch.js js/settings.js js/trip.js"
OPTS="--omit-header --no-location --no-wrap -j"
for lang in de_DE es_ES fi_FI fr_FR ja_JP lt_LT pl_PL pt_BR sv_SE ru_RU; do
  POPATH=locale/$lang.utf8/LC_MESSAGES
  echo $lang
  touch $POPATH/new.po
  xgettext $OPTS -o $POPATH/new.po $PHP
  xgettext $OPTS -L C -o $POPATH/new.po $JS
  # Filter out obsoleted strings
  msgmerge -N $POPATH/messages.po $POPATH/new.po >$POPATH/newest.po
  grep -v "^#~" $POPATH/newest.po >$POPATH/messages.po
  rm $POPATH/new.po $POPATH/newest.po
done

touch locale/new.po
xgettext $OPTS -o locale/template.po $PHP
xgettext $OPTS -L C -o locale/template.po $JS
msgmerge -N locale/template.po locale/new.po >locale/newest.po
grep -v "^#~" locale/newest.po >locale/messages.po
rm locale/new.po locale/newest.po
