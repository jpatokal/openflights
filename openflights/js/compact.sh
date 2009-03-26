#!/bin/sh
# Crunch the .orig files to save space and loading time
jscompact -la <utilities.js.orig >utilities.js
jscompact -la <scriptaculous.js.orig >scriptaculous.js

