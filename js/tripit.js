/**
 * tripit.js - TripIt integration for OpenFlights
 * Andrew Chen - achen.code on big-G's email service
 */

// Constants used elsewhere in this file.
var CONST = {
  DEBUG: false,
  IMPORT_URL: "/php/submit.php",
  // HARD_FAIL means "don't retry", whereas SOFT_FAIL means "try again later".
  CODE_HARD_FAIL: -1,
  CODE_ADDOK: 1,
};

/**
 * Submit a segment import form
 * @param segmentId TripIt segment ID to import
 */
function importFlight(segmentId) {
  var form = $("#import" + segmentId);
  if (form == null) {
    // Shouldn't happen, but let's be defensive.
    setStatus(segmentId, "Internal Error: Couldn't find segment " + segmentId);
    return;
  }

  // Serialize the form into a request.
  var params = form.serialize();

  if (CONST.DEBUG) {
    console.log("importFlight: params=" + params);
  }

  $.post(CONST.IMPORT_URL, params, importFlightComplete(segmentId));
}

/**
 * Callback upon successful import.
 * @param segmentId
 */
var importFlightComplete = function (segmentId) {
  return function (data, responseText, jqXHR) {
    var result = data.split(";"),
      code = result[0],
      text = result[1];
    setStatus(segmentId, "<B>" + text + "</B>");

    var showOverlay = false;
    var overlayImage;
    if (code == CONST.CODE_ADDOK) {
      // Successful add; show checkmark.
      showOverlay = true;
      overlayImage = "Checkmark_green.80px.png";
    } else if (code == CONST.CODE_HARD_FAIL) {
      // Fatal error, don't try again.
      showOverlay = true;
      overlayImage = "Red_X.64px.png";
    }

    if (showOverlay) {
      $("#import" + segmentId + " :input").attr("disabled", true);
      $("#import" + segmentId).block({
        message:
          '<img style="height:64px; width: auto" src="/img/' +
          overlayImage +
          '">',
        css: {
          cursor: "default",
          border: "none",
          padding: "15px",
          // Set this to #000 to add a dark box around the checkbox
          backgroundColor: "transparent",
          "-webkit-border-radius": "10px",
          "-moz-border-radius": "10px",
          // Set opacity to .5 or .6 if box enabled above.
          opacity: 1,
          color: "#fff",
        },
        overlayCSS: {
          height: $("#segment" + segmentId).height(),
          cursor: "default",
        },
      });
    }
  };
};

/**
 * Shortcut for marking a segment as already imported when loading a TripIt list page.
 * @param segmentId
 */
function markSegmentImported(segmentId) {
  importFlightComplete(segmentId)(
    CONST.CODE_ADDOK + ";Segment already imported.",
    null,
    null
  );
}

/**
 * Shortcut for marking a segment as invalid for import.  This shouldn't normally happen.
 * @param segmentId
 */
function markSegmentInvalid(segmentId) {
  importFlightComplete(segmentId)(
    CONST.CODE_HARD_FAIL + ";Insufficient data to import this segment.",
    null,
    null
  );
}

/**
 * Set the status field for a given segment to message.
 * @param segmentId
 * @param message
 */
function setStatus(segmentId, message) {
  var statusSpan = document.getElementById("input_status" + segmentId);
  if (statusSpan != null) {
    statusSpan.innerHTML = message;
  }
}

/**
 * Import multiple segments at once.  Under the covers, this still calls {@link #importFlight},
 * so each segment will result in a call to the service to import.
 * @param flights Array of flights to import.
 */
function importFlights(flights) {
  for (var i = 0; i < flights.length; i++) {
    importFlight(flights[i]);
  }
}

/**
 * Add a button to the DOM to import all segments for a given trip id.
 * @param importAllButtonValue Text for the Import All button.  This should be localized.
 * @param tripId ID of the trip.
 * @param segments Array of segments to be imported when this button is clicked.
 */
function addImportAllButton(importAllButtonValue, tripId, segments) {
  var importAllDiv = document.getElementById("import_all_" + tripId);
  if (importAllDiv == null) {
    if (CONST.DEBUG) {
      console.log(
        "Couldn't find div to insert Import All button for trip " + tripId
      );
    }
    return;
  }

  importAllDiv.innerHTML =
    '<input type="button" onclick="importFlights(new Array(' +
    segments +
    '))" value="' +
    importAllButtonValue +
    '">';
}

/**
 * Init method for the rendezvous instruction page.
 */
function rendezvousPageInit() {
  $("#loginPathPartner").click(function () {
    $("#loginPathSelection").hide();
    $("#loginPathPartnerHelp").show();
  });

  $("#loginPathNative").click(function () {
    window.location.href = "/php/tripit_rendezvous_start.php";
  });
}

/**
 * Popup a window with TripIt's login page.  This is used for rendezvous with partner logins.
 */
function openTripItLogin() {
  window.open(
    "https://www.tripit.com/account/login",
    "TripItLogin",
    "width=1000,height=550,scrollbars=yes"
  );
}
