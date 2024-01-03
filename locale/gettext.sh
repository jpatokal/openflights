#!/bin/sh
#
# Collect gettext strings from all PHP/JavaScript files and update existing .po files
# GNU xgettext does not recognize JavaScript, but oddly enough C seems to parse it (almost) fine...
#
# Should be run from repo root; ./locale/gettext.sh
#

buildlocale() {
  PO_NEW=$1/new.po
  touch $PO_NEW

  xgettext $OPTS -o $PO_NEW $PHP
  xgettext $OPTS -L C -o $PO_NEW $JS

  # Filter out obsoleted strings
  PO_NEWEST=$1/newest.po
  PO_MESSAGES=$1/messages.po

  msgmerge -N $PO_MESSAGES $PO_NEW >$PO_NEWEST
  grep -v "^#~" $PO_NEWEST >$PO_MESSAGES
  rm $PO_NEW $PO_NEWEST

  # Make .mo files
  MO_FILE=$1/messages.mo
  msgfmt -o $MO_FILE $PO_MESSAGES
}

PHP="about.php data.php donate.php faq.php index.php sidebar.php help/*php html/*php php/*php"
JS="openflights.js js/alsearch.js js/apsearch.js js/functions.js js/settings.js js/trip.js"
OPTS="--omit-header --no-location --no-wrap -j"

for lang in de_DE en_GB en_US es_ES fi_FI fr_FR ja_JP lt_LT nl_NL pl_PL pt_BR sv_SE ru_RU; do
  echo $lang
  buildlocale locale/$lang.utf8/LC_MESSAGES
done

buildlocale locale