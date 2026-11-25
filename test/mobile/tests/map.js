"use strict";

/**
 * Map tests for the OpenLayers 10 mobile map.
 *
 * These run in demo mode (the runner loads the app with ?mapdebug=1, which
 * exposes the map context as window.__openflightsMap for inspection). They
 * cover the pieces that used to be manual smoke checks: control wiring,
 * clustering, line rendering, airport popups and the layer switcher.
 */

const h = require("../lib/harness");

async function openMap(page) {
  await h.click(page, '.tabbar__tab[data-view="map"]');
  await page.waitForSelector("#view-map.is-active", { timeout: 10000 });
  await page.waitForSelector("#map .ol-viewport", { timeout: 20000 });
  await page.waitForFunction(() => Boolean(window.__openflightsMap), {
    timeout: 20000,
  });

  // A failed earlier test could leave a popup or chooser open; start clean.
  await page.evaluate(() => {
    const popupClose = document.getElementById("airport-popup-close");
    if (popupClose) popupClose.click();
    const chooser = document.getElementById("airport-chooser");
    if (chooser && !chooser.hidden) {
      const close = document.getElementById("chooser-close");
      if (close) close.click();
    }
  });

  // Let the initial fit and world clamp settle.
  await page.waitForFunction(
    () => {
      const ctx = window.__openflightsMap;
      return ctx && ctx.view.getZoom() != null;
    },
    { timeout: 10000 }
  );
  await h.sleep(250);
}

async function mapState(page) {
  return page.evaluate(() => {
    const ctx = window.__openflightsMap;
    const rendered = ctx.airportLayerSource.getFeatures();
    return {
      flights: ctx.flightSource.getFeatures().length,
      airports: ctx.airportSource.getFeatures().length,
      rendered: rendered.length,
      clusters: rendered.filter((f) => (f.get("features") || []).length > 1)
        .length,
      zoom: ctx.view.getZoom(),
      extent: ctx.view.calculateExtent(ctx.map.getSize()),
      satellite: ctx.satellite.getVisible(),
      political: ctx.political.getVisible(),
      selected: ctx.select.getFeatures().getLength(),
      canvas: Array.from(document.querySelectorAll("#map canvas")).map((c) => ({
        width: c.width,
        height: c.height,
      })),
    };
  });
}

// Find a screen-space pixel that hits either a single airport or a cluster.
async function findFeaturePixel(page, kind) {
  return page.evaluate((kind) => {
    const ctx = window.__openflightsMap;
    const target = ctx.map.getTargetElement();
    const rect = target.getBoundingClientRect();

    for (const feature of ctx.airportLayerSource.getFeatures()) {
      const members = feature.get("features") || [];
      const memberCount = members.length || 1;
      if (kind === "cluster" ? memberCount <= 1 : memberCount > 1) {
        continue;
      }

      const pixel = ctx.map.getPixelFromCoordinate(
        feature.getGeometry().getCoordinates()
      );
      if (
        pixel[0] < 0 ||
        pixel[1] < 0 ||
        pixel[0] > rect.width ||
        pixel[1] > rect.height
      ) {
        continue;
      }

      const hit = ctx.map.forEachFeatureAtPixel(pixel, (f) => f);
      if (hit) {
        return {
          x: rect.left + pixel[0],
          y: rect.top + pixel[1],
          members: (hit.get("features") || []).length,
        };
      }
    }
    return null;
  }, kind);
}

module.exports = [
  {
    name: "map: viewport, controls and layer switcher initialise",
    async run({ page }) {
      await openMap(page);

      for (const control of [
        ".ol-zoom",
        ".layer-switcher",
        ".ol-scale-line",
        ".ol-attribution",
      ]) {
        const found = await page.$$(`#map ${control}`);
        h.assert(found.length > 0, "missing map control " + control);
      }

      const state = await mapState(page);
      h.assert(state.canvas.length > 0, "map canvas missing");
      h.assert(
        state.canvas.every((c) => c.width > 0 && c.height > 0),
        "map canvas has no size"
      );

      const labels = await page.$$eval("#map .layer-switcher label", (els) =>
        els.map((el) => el.textContent.trim())
      );
      for (const label of [
        "Base maps",
        "Political",
        "Satellite",
        "Flights",
        "Airports",
      ]) {
        h.assert(labels.includes(label), "layer switcher missing " + label);
      }

      const chrome = await page.evaluate(() => {
        const readButton = (selector) => {
          const el = document.querySelector(selector);
          if (!el) return null;
          const rect = el.getBoundingClientRect();
          return {
            text: el.textContent.trim(),
            color: getComputedStyle(el).color,
            width: rect.width,
            height: rect.height,
          };
        };
        const attribution = document.querySelector("#map .ol-attribution");
        const list = attribution && attribution.querySelector("ul");
        const attribButton = attribution && attribution.querySelector("button");
        const rect = attribution && attribution.getBoundingClientRect();
        const buttonRect = attribButton && attribButton.getBoundingClientRect();
        return {
          zoomIn: readButton("#map .ol-zoom-in"),
          zoomOut: readButton("#map .ol-zoom-out"),
          attribution: attribution
            ? {
                collapsed: attribution.classList.contains("ol-collapsed"),
                text: list ? list.textContent.trim() : "",
                width: rect.width,
                height: rect.height,
                button: attribButton
                  ? {
                      title: attribButton.title,
                      width: buttonRect.width,
                      height: buttonRect.height,
                    }
                  : null,
              }
            : null,
        };
      });

      h.assert(chrome.zoomIn, "zoom-in button missing");
      h.assert(chrome.zoomOut, "zoom-out button missing");
      h.assertEqual(chrome.zoomIn.text, "+", "zoom-in glyph");
      h.assert(chrome.zoomOut.text.length > 0, "zoom-out glyph missing");
      h.assertEqual(
        chrome.zoomIn.color,
        "rgb(255, 255, 255)",
        "zoom-in text colour"
      );
      h.assertEqual(
        chrome.zoomOut.color,
        "rgb(255, 255, 255)",
        "zoom-out text colour"
      );
      h.assert(
        chrome.zoomIn.width > 0 && chrome.zoomIn.height > 0,
        "zoom-in button has no size"
      );

      h.assert(chrome.attribution, "attribution control missing");
      h.assert(
        chrome.attribution.collapsed,
        "attribution should collapse into its button"
      );
      h.assert(chrome.attribution.button, "attribution button missing");
      h.assert(
        chrome.attribution.button.width > 0 &&
          chrome.attribution.button.height > 0,
        "attribution button has no size"
      );
      h.assertIncludes(chrome.attribution.text, "CARTO", "attribution text");
      h.assert(
        chrome.attribution.width > 0 && chrome.attribution.height > 0,
        "attribution has no size"
      );
    },
  },
  {
    name: "map: layer switcher aligns and shows a visible close arrow",
    async run({ page }) {
      await openMap(page);

      const closed = await page.evaluate(() => {
        const switcher = document.querySelector("#map .layer-switcher");
        const zoom = document.querySelector("#map .ol-zoom");
        const button = switcher.querySelector(":scope > button");
        const switcherRect = switcher.getBoundingClientRect();
        const zoomRect = zoom.getBoundingClientRect();
        const buttonRect = button.getBoundingClientRect();
        return {
          shown: switcher.classList.contains("shown"),
          switcherTop: switcherRect.top,
          zoomTop: zoomRect.top,
          buttonWidth: buttonRect.width,
          buttonHeight: buttonRect.height,
        };
      });

      h.assert(!closed.shown, "layer switcher should start closed");
      h.assert(
        closed.buttonWidth > 0 && closed.buttonHeight > 0,
        "layer switcher toggle has no size"
      );
      h.assert(
        Math.abs(closed.switcherTop - closed.zoomTop) <= 1,
        "layer switcher is not aligned with the top of the map controls"
      );

      await h.click(page, "#map .layer-switcher > button");
      await page.waitForFunction(
        () =>
          document
            .querySelector("#map .layer-switcher")
            .classList.contains("shown"),
        { timeout: 5000 }
      );

      const open = await page.evaluate(() => {
        const switcher = document.querySelector("#map .layer-switcher");
        const button = switcher.querySelector(":scope > button");
        const panel = switcher.querySelector(".panel");
        const style = getComputedStyle(button);
        const before = getComputedStyle(button, "::before");
        const buttonRect = button.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        return {
          arrow: before.content,
          color: style.color,
          background: style.backgroundColor,
          width: buttonRect.width,
          height: buttonRect.height,
          panelDisplay: getComputedStyle(panel).display,
          panelHeight: panelRect.height,
        };
      });

      h.assert(
        open.arrow !== "none" && open.arrow !== "normal",
        "close arrow is not rendered"
      );
      h.assertEqual(open.color, "rgb(255, 255, 255)", "close arrow colour");
      h.assert(open.width > 0 && open.height > 0, "close button has no size");
      h.assert(
        open.panelDisplay !== "none" && open.panelHeight > 0,
        "layer panel is not visible"
      );

      await h.click(page, "#map .layer-switcher > button");
      await page.waitForFunction(
        () =>
          !document
            .querySelector("#map .layer-switcher")
            .classList.contains("shown"),
        { timeout: 5000 }
      );
    },
  },
  {
    name: "map: base map tile URLs resolve without placeholders",
    async run({ page }) {
      await openMap(page);
      const urls = await page.evaluate(() => {
        const ctx = window.__openflightsMap;
        const projection = ctx.view.getProjection();
        const resolve = (layer) =>
          layer.getSource().getTileUrlFunction()([2, 2, 2], 1, projection);
        return {
          political: String(resolve(ctx.political)),
          satellite: String(resolve(ctx.satellite)),
        };
      });

      for (const [name, url] of Object.entries(urls)) {
        h.assertMatch(url, /^https:\/\//, name + " tile URL scheme");
        h.assert(
          !url.includes("$"),
          name + " tile URL has a stray $ placeholder"
        );
        h.assert(
          !url.includes("{"),
          name + " tile URL has unresolved placeholders"
        );
        h.assertMatch(
          url,
          /\/\d+\/\d+\/\d+\.png/,
          name + " tile URL coordinates"
        );
      }
    },
  },
  {
    name: "map: base map tiles wrap across the date line",
    async run({ page }) {
      await openMap(page);
      const wrap = await page.evaluate(() => {
        const ctx = window.__openflightsMap;
        return {
          politicalExtent: Boolean(ctx.political.getExtent()),
          satelliteExtent: Boolean(ctx.satellite.getExtent()),
          politicalWrap: ctx.political.getSource().getWrapX(),
          satelliteWrap: ctx.satellite.getSource().getWrapX(),
        };
      });

      h.assert(
        !wrap.politicalExtent,
        "political layer extent would clip tiles at the date line"
      );
      h.assert(
        !wrap.satelliteExtent,
        "satellite layer extent would clip tiles at the date line"
      );
      h.assert(wrap.politicalWrap, "political source must enable wrapX");
      h.assert(wrap.satelliteWrap, "satellite source must enable wrapX");
    },
  },
  {
    name: "map: flight lines and clustered airports render",
    async run({ page }) {
      await openMap(page);
      const state = await mapState(page);

      h.assert(state.flights > 0, "expected flight line features");
      h.assert(state.airports > 0, "expected airport features");
      h.assert(
        state.rendered <= state.airports,
        "clustering should not add rendered features"
      );
      h.assert(state.clusters > 0, "expected at least one airport cluster");
      h.assert(
        state.clusters < state.airports,
        "clustering should collapse overlapping airports"
      );
      h.assert(state.zoom >= 2, "zoom fell below the minimum level");

      const validity = await page.evaluate(() => {
        const ctx = window.__openflightsMap;
        const airportsValid = ctx.airportSource
          .getFeatures()
          .every(
            (f) =>
              Boolean(f.get("code")) &&
              Boolean(f.get("color")) &&
              typeof f.get("radius") === "number"
          );
        const linesValid = ctx.flightSource.getFeatures().every((f) => {
          const coords = f.getGeometry().getCoordinates();
          return Array.isArray(coords) && coords.length > 1;
        });
        return { airportsValid, linesValid };
      });
      h.assert(
        validity.airportsValid,
        "airport features lack style attributes"
      );
      h.assert(validity.linesValid, "flight features lack line geometry");

      const world = await page.evaluate(
        () => window.OpenFlights.Maps.WORLD_EXTENT
      );
      h.assert(
        state.extent[1] >= world[1] - 1 && state.extent[3] <= world[3] + 1,
        "viewport escaped the world vertically"
      );
    },
  },
  {
    name: "map: clicking an airport opens its popup",
    async run({ page }) {
      await openMap(page);
      const pixel = await findFeaturePixel(page, "single");
      h.assert(pixel, "no visible unclustered airport found");

      await page.mouse.click(pixel.x, pixel.y);
      await page.waitForSelector(".airport-popup__name", { timeout: 10000 });

      const name = await page.$eval(".airport-popup__name", (el) =>
        el.textContent.trim()
      );
      h.assert(name.length > 0, "airport popup has no name");
      h.assertEqual(
        (await mapState(page)).selected,
        1,
        "selected airport count"
      );

      await h.click(page, "#airport-popup-close");
      await page.waitForFunction(
        () => !document.querySelector(".airport-popup__name"),
        { timeout: 10000 }
      );
      h.assertEqual(
        (await mapState(page)).selected,
        0,
        "selection cleared after closing the popup"
      );
    },
  },
  {
    name: "map: clicking a cluster opens the airport chooser",
    async run({ page }) {
      await openMap(page);
      const pixel = await findFeaturePixel(page, "cluster");
      h.assert(pixel, "no visible airport cluster found");
      h.assert(pixel.members > 1, "hit feature is not a cluster");

      await page.mouse.click(pixel.x, pixel.y);
      await page.waitForSelector("#airport-chooser:not([hidden])", {
        timeout: 10000,
      });

      const rows = await page.$$eval(
        "#chooser-list .chooser-row",
        (els) => els.length
      );
      h.assertEqual(rows, pixel.members, "chooser row count");
      h.assert(rows > 1, "chooser should list multiple airports");

      const subtitle = await page.$eval("#chooser-subtitle", (el) =>
        el.textContent.trim()
      );
      h.assertMatch(
        subtitle,
        /\d+\s+airports in this area/,
        "chooser subtitle"
      );

      await h.click(page, "#chooser-close");
      await page.waitForFunction(
        () => document.getElementById("airport-chooser").hidden,
        { timeout: 10000 }
      );
      h.assertEqual(
        (await mapState(page)).selected,
        0,
        "selection cleared after closing the chooser"
      );
    },
  },
  {
    name: "map: layer switcher toggles the satellite base map",
    async run({ page }) {
      await openMap(page);
      const before = await mapState(page);
      h.assert(before.political, "political layer should start visible");
      h.assert(!before.satellite, "satellite layer should start hidden");

      const clickedSatellite = await page.evaluate(() => {
        const label = Array.from(
          document.querySelectorAll("#map .layer-switcher label")
        ).find((el) => el.textContent.trim() === "Satellite");
        if (!label) return false;
        label.click();
        return true;
      });
      h.assert(clickedSatellite, "satellite layer toggle not found");
      await page.waitForFunction(
        () => window.__openflightsMap.satellite.getVisible(),
        { timeout: 10000 }
      );

      const satellite = await mapState(page);
      h.assert(satellite.satellite, "satellite layer visible after toggle");
      h.assert(
        !satellite.political,
        "political layer hidden while satellite is visible"
      );

      await page.evaluate(() => {
        const label = Array.from(
          document.querySelectorAll("#map .layer-switcher label")
        ).find((el) => el.textContent.trim() === "Political");
        label.click();
      });
      await page.waitForFunction(
        () => !window.__openflightsMap.satellite.getVisible(),
        { timeout: 10000 }
      );
      h.assert(
        (await mapState(page)).political,
        "political layer restored after toggle"
      );
    },
  },
];
