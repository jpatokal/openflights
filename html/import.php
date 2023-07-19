<?php
require_once "../php/locale.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <title><?php echo sprintf(_('OpenFlights: %s'), _('Import data')); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
  </head>

  <body>
    <div id="contexthelp">
      <h1><?php echo _("Import data"); ?></h1>

      <p><?php echo _("OpenFlights can import flights from three sources:"); ?></p>

      <form name="importform" action="/php/import.php" enctype="multipart/form-data" method="post"><p>
      <input type="hidden" name="MAX_FILE_SIZE" value="100000" />

    <table style='border-spacing: 5'>
        <tr>
          <td style='vertical-align: top'></td>
          <td><p><?php
            printf(
                _("<b>TripIt</b> &mdash; Connect directly to TripIt.com to import past and future trips, in bulk or individually. Duplicates are detected.")
            ); ?>
            <input type="button" value="<?php
                echo _("Import from Tripit");
            ?>" onClick="window.location='/php/tripit_list_trips.php'"/></p>
          </td>
        </tr>
        <tr>
          <td style='vertical-align: top'><input type="radio" name="fileType" value="FM" CHECKED></td>
          <td>
              <p><?php
                  echo _("<b>FlightMemory (.html)</b> &mdash; FlightMemory does not have an export function, but OpenFlights can 'screen-scrape' its FlightData pages. Log into your FlightMemory account, go to the FlightData page, and select 'Save Web Page' in your browser. Upload the resulting HTML file. Repeat once for each page of data."); ?>
              </p>
              <p><?php
                  echo _('<i>Warning</i>: Be sure to set your FlightMemory language to <b>English</b> before saving the pages.');
              ?></p>
              <p><?php
                  echo _('<i>Note</i>: There is <b>no duplicate detection</b> at this time. If you import a FlightMemory file twice, all flights in it will be added twice.');
                  ?></p>
              <p><?php
                  printf(
                      _("<i>Note</i>: The <b>FlightMemory format</b> changes from time to time. Please <%s>report</a> any bugs or oddities, and be sure to include the exact error and the 'Tmpfile' value from the top of the import page."),
                      "a href='/contact.html'"
                  ); ?></p>
          </td>
        </tr>
        <tr>
          <td style='vertical-align: top'><input type="radio" name="fileType" value="CSV"></td>
          <td><p><?php printf(
              _("<b>OpenFlights (.csv)</b> &mdash; Comma-separated value, exported from OpenFlights 'Export' or 'Backup') and easily edited or created in Excel or any other spreadsheet. See <%s>format specification</a>."),
              'a href="#help" onClick="javascript:window.open("/help/csv.php", "CSV", "width=500, height=400,scrollbars=yes")"'
          ); ?></p>
          </td>
        </tr>
    </table>

<h4><?php echo _("File to upload"); ?></h4>
  <p><input type="file" name="userfile" size="30"><b><?php
      echo _("Keep historical airline names?"); ?></b><input type="checkbox" name="historyMode" value="yes">
  </p>

<p><?php
    echo _("OpenFlights normally tries to match airlines and flight numbers to known/current airlines. If you check the box above, all airline names will be preserved <i>exactly</i> as they were before, typos and all.");
    printf(
        "<p>" . _("You will be given a chance to review the flights before they are actually imported. See %s to backup or clear your flights before importing.") . "</p>",
        "<input type='button' value='Settings' align='middle' onclick='JavaScript:parent.opener.settings()'>"
    ); ?>

    <input type="submit" name="action" value="<?php echo _("Upload"); ?>">
    <input type="button" value="<?php echo _("Cancel"); ?>" onClick="window.close()">
  </form>

  <div id="miniresultbox"></div><br>
  </div>

  </body>
</html>
