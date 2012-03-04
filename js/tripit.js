/**
 * tripit.js - TripIt integration for OpenFlights
 * Andrew Chen - achen.code on big-G's email service
 */

// Constants used elsewhere in this file.
var CONST = {
  DEBUG: false,
  IMPORT_URL: "/php/submit.php",
  CODE_ADDOK: 1
};

/**
 * Submit a segment import form
 * @param segmentId TripIt segment ID to import
 */
function importFlight(segmentId) {
  var form = $("#import" + segmentId);
  if(form == null) {
    // Shouldn't happen, but let's be defensive.
    setStatus(segmentId, "Internal Error: Couldn't find segment " + segmentId);
    return;
  }

  // Serialize the form into a request.
  var params = form.serialize();

  if(CONST.DEBUG) {
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
    var result = data.split(";");
    code = result[0];
    text = result[1];
    setStatus(segmentId, '<B>' + text + '</B>');

    if(code == CONST.CODE_ADDOK) {
      $('#import' + segmentId + ' :input').attr("disabled", true);
      $('#import' + segmentId).block({
        message:'<img width="80" height="64" src="/img/Checkmark_green.80px.png">',
        css:{
          cursor:'default',
          border:'none',
          padding:'15px',
          // Set this to #000 to add a dark box around the checkbox
          backgroundColor:'transparent',
          '-webkit-border-radius':'10px',
          '-moz-border-radius':'10px',
          // Set opacity to .5 or .6 if box enabled above.
          opacity:1,
          color:'#fff'
        },
        overlayCSS:{
          height:$('#segment' + segmentId).height(),
          cursor:'default'
        }
      })
    }
  }
};

/**
 * Shortcut for marking a segment as already imported when loading a TripIt list page.
 * @param segmentId
 */
function markSegmentImported(segmentId) {
  importFlightComplete(segmentId)("1;Segment already imported.", null, null);
}

/**
 * Set the status field for a given segment to message.
 * @param segmentId
 * @param message
 */
function setStatus(segmentId, message) {
  var statusSpan = document.getElementById("input_status" + segmentId);
  if(statusSpan != null) {
    statusSpan.innerHTML = message;
  }
}
