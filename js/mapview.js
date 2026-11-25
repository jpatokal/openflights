/**
 * Shared OpenLayers 10 map helpers.
 *
 * Both the mobile app (mobile/app.js) and, later, the legacy desktop app
 * (openflights.js) build their maps through this module so the OL10 setup,
 * layer wiring, selection and overlay behaviour live in one place.
 *
 * Requires the OpenLayers 10 full build (/js/ol.min.js) to be loaded first.
 * Returns a plain context object rather than a class so callers can reach the
 * underlying map/view/sources when they need to apply app-specific styles.
 */
(function (global) {
  "use strict";

  var OpenFlights = global.OpenFlights || (global.OpenFlights = {});

  // Spherical Mercator world extent (EPSG:3857), roughly +/-20037508 m.
  var WORLD_EXTENT = [
    -20037508.342789244, -20037508.342789244, 20037508.342789244,
    20037508.342789244,
  ];

  /**
   * Create a map with the political/satellite base layers plus empty flight
   * and airport vector sources.
   *
   * @param {string|Element} target
   * @param {Object} options
   * @returns {Object} map context
   */
  function createMap(target, options) {
    options = options || {};

    var maxZoom = options.maxZoom == null ? 19 : options.maxZoom;
    var minZoom = options.minZoom == null ? 2 : options.minZoom;

    // Don't set a layer extent here: it would clip rendering to the primary
    // world copy and leave a bare strip past the date line. wrapX plus the view
    // clamp below handle horizontal wrapping and vertical over-zoom instead.
    var political = new ol.layer.Tile({
      source: new ol.source.XYZ({
        url: options.politicalUrl,
        attributions: options.politicalAttribution,
        maxZoom: maxZoom,
        wrapX: true,
      }),
      properties: {
        title: options.politicalTitle || "Political",
        type: "base",
      },
    });

    var satellite = new ol.layer.Tile({
      source: new ol.source.XYZ({
        url: options.satelliteUrl,
        attributions: options.satelliteAttribution,
        maxZoom: maxZoom,
        wrapX: true,
      }),
      visible: false,
      properties: {
        title: options.satelliteTitle || "Satellite",
        type: "base",
      },
    });

    var flightSource = new ol.source.Vector();
    var flightLayer = new ol.layer.Vector({
      source: flightSource,
      properties: { title: options.flightTitle || "Flights" },
    });

    var airportSource = new ol.source.Vector();
    var airportLayerSource = options.clusterDistance
      ? new ol.source.Cluster({
          source: airportSource,
          distance: options.clusterDistance,
        })
      : airportSource;
    var airportLayer = new ol.layer.Vector({
      source: airportLayerSource,
      properties: { title: options.airportTitle || "Airports" },
    });

    var baseGroup = new ol.layer.Group({
      properties: { title: options.baseTitle || "Base maps" },
      layers: [political, satellite],
    });

    var view = new ol.View({
      center: options.center || [0, 0],
      zoom: options.zoom == null ? minZoom : options.zoom,
      minZoom: minZoom,
      maxZoom: maxZoom,
    });

    var map = new ol.Map({
      target: target,
      layers: [baseGroup, flightLayer, airportLayer],
      view: view,
      controls: options.controls || [],
    });

    var ctx = {
      map: map,
      view: view,
      political: political,
      satellite: satellite,
      flightSource: flightSource,
      flightLayer: flightLayer,
      airportSource: airportSource,
      airportLayerSource: airportLayerSource,
      airportLayer: airportLayer,
      baseGroup: baseGroup,
      minZoom: minZoom,
      maxZoom: maxZoom,
      select: null,
      _clamping: false,
    };

    // Keep the viewport from wandering outside the world at low zoom levels,
    // preserving the OL2-era clamp that avoided 404 tile rows above/below the
    // poles on tall (portrait) viewports.
    clampViewToWorld(ctx);
    map.on("moveend", function () {
      clampViewToWorld(ctx);
    });
    map.on("change:size", function () {
      clampViewToWorld(ctx);
    });

    return ctx;
  }

  /**
   * Zoom in as needed so the viewport fits inside the world vertically, then
   * nudge the centre back within the poles.
   */
  function clampViewToWorld(ctx) {
    if (!ctx || ctx._clamping || !ctx.map || !ctx.view) {
      return;
    }

    var size = ctx.map.getSize();
    if (!size || !size[1]) {
      return;
    }

    ctx._clamping = true;
    try {
      var worldHeight = WORLD_EXTENT[3] - WORLD_EXTENT[1];
      var current = ctx.view.getZoom();
      var zoom = Math.max(
        ctx.minZoom,
        Math.min(current == null ? ctx.minZoom : current, ctx.maxZoom)
      );

      for (; zoom < ctx.maxZoom; zoom += 1) {
        var resolution = ctx.view.getResolutionForZoom(zoom);
        if (resolution == null || size[1] * resolution <= worldHeight) {
          break;
        }
      }
      if (zoom !== current) {
        ctx.view.setZoom(zoom);
      }

      var extent = ctx.view.calculateExtent(size);
      var center = ctx.view.getCenter();
      if (!extent || !center) {
        return;
      }

      var halfHeight = (extent[3] - extent[1]) / 2;
      var northLimit = WORLD_EXTENT[3] - halfHeight;
      var southLimit = WORLD_EXTENT[1] + halfHeight;
      var y = center[1];
      if (y > northLimit) {
        y = northLimit;
      } else if (y < southLimit) {
        y = southLimit;
      }
      if (y !== center[1]) {
        ctx.view.setCenter([center[0], y]);
      }
    } finally {
      ctx._clamping = false;
    }
  }

  /**
   * Fit the view to a vector source's extent, or reset to the minimum zoom
   * when the source is empty.
   */
  function fitToSource(ctx, source, options) {
    options = options || {};
    var extent = source.getExtent();
    if (!extent || ol.extent.isEmpty(extent)) {
      ctx.view.setZoom(ctx.minZoom);
      return;
    }
    ctx.view.fit(extent, {
      padding: options.padding || [0, 0, 0, 0],
      maxZoom: options.maxZoom == null ? ctx.maxZoom : options.maxZoom,
      duration: options.duration == null ? 0 : options.duration,
    });
  }

  /**
   * Wire a click-select interaction on the airport layer.
   */
  function createSelection(ctx, onSelect, onUnselect) {
    var select = new ol.interaction.Select({
      layers: [ctx.airportLayer],
      condition: ol.events.condition.click,
      // The mobile app draws its own selection affordance, so don't apply the
      // default edit style to selected features.
      style: null,
    });
    select.on("select", function (event) {
      if (event.selected.length) {
        onSelect(event.selected[0]);
      } else if (event.deselected.length && onUnselect) {
        onUnselect(event.deselected[0]);
      }
    });
    ctx.map.addInteraction(select);
    ctx.select = select;
    return select;
  }

  /**
   * Create a DOM overlay anchored to a map coordinate.
   */
  function createOverlay(ctx, element, options) {
    options = options || {};
    var overlay = new ol.Overlay({
      element: element,
      positioning: options.positioning || "bottom-center",
      offset: options.offset || [0, -8],
      stopEvent: true,
      autoPan:
        options.autoPan === false ? false : { animation: { duration: 250 } },
    });
    ctx.map.addOverlay(overlay);
    return overlay;
  }

  /**
   * Features represented by a cluster feature (or the feature itself).
   */
  function clusterMembers(feature) {
    if (!feature) {
      return [];
    }
    var members = feature.get("features");
    return members && members.length ? members : [feature];
  }

  /**
   * Clear the current selection without firing app callbacks.
   */
  function clearSelection(ctx) {
    if (ctx && ctx.select) {
      ctx.select.getFeatures().clear();
    }
  }

  OpenFlights.Maps = {
    WORLD_EXTENT: WORLD_EXTENT,
    createMap: createMap,
    clampViewToWorld: clampViewToWorld,
    fitToSource: fitToSource,
    createSelection: createSelection,
    createOverlay: createOverlay,
    clusterMembers: clusterMembers,
    clearSelection: clearSelection,
  };
})(typeof window !== "undefined" ? window : this);
