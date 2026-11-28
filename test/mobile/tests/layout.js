"use strict";

/**
 * Large-screen pane layout: Map, Flights and Overview stack vertically, one or
 * two panes are open at a time, and each pane can grow, shrink or maximise.
 * Each test restores the suite's phone-sized viewport before finishing.
 */

const h = require("../lib/harness");

const NARROW = { width: 390, height: 844 };
const WIDE = { width: 1280, height: 900 };

async function enterLarge(page) {
  await page.setViewport(WIDE);
  // Reload so every test starts from the documented default: map open only.
  await page.reload({ waitUntil: "domcontentloaded" });
  await h.waitForAppReady(page);
  await page.waitForFunction(
    () => {
      const ctx = window.__openflightsMap;
      const size = ctx && ctx.map ? ctx.map.getSize() : null;
      return (
        document.body.classList.contains("pane-layout") &&
        document.getElementById("view-map").classList.contains("is-open") &&
        size &&
        size[1] > 0 &&
        ctx.airportLayerSource.getFeatures().length > 0
      );
    },
    { timeout: 20000 }
  );
  await h.sleep(200);
}

async function panes(page) {
  return page.evaluate(() => {
    const names = ["map", "flights", "overview"];
    const out = {};
    names.forEach((name) => {
      const el = document.getElementById("view-" + name);
      const rect = el.getBoundingClientRect();
      out[name] = {
        open: el.classList.contains("is-open"),
        closed: el.classList.contains("is-closed"),
        top: Math.round(rect.top),
        height: Math.round(rect.height),
      };
    });
    const ctx = window.__openflightsMap;
    return {
      map: out.map,
      flights: out.flights,
      overview: out.overview,
      order: names.slice().sort((a, b) => out[a].top - out[b].top),
      mapSize: ctx && ctx.map ? ctx.map.getSize() : null,
      tabbar: getComputedStyle(document.querySelector(".tabbar")).display,
      bars: Object.fromEntries(
        names.map((name) => [
          name,
          getComputedStyle(
            document.querySelector("#view-" + name + " .pane-bar")
          ).display,
        ])
      ),
      buttons: Object.fromEntries(
        names.map((name) => {
          const size = document.querySelector(
            '[data-pane-size="' + name + '"]'
          );
          const hide = document.querySelector(
            '[data-pane-hide="' + name + '"]'
          );
          return [
            name,
            {
              size: size.disabled,
              hide: hide.disabled,
              state: size.dataset.state || "",
            },
          ];
        })
      ),
    };
  });
}

// First visible airport/cluster of the given kind, in page coordinates.
async function findFeaturePixel(page, kind) {
  return page.evaluate((kind) => {
    const ctx = window.__openflightsMap;
    const rect = ctx.map.getTargetElement().getBoundingClientRect();
    for (const feature of ctx.airportLayerSource.getFeatures()) {
      const members = feature.get("features") || [];
      const memberCount = members.length || 1;
      if (kind === "cluster" ? memberCount <= 1 : memberCount > 1) continue;
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
      if (ctx.map.forEachFeatureAtPixel(pixel, (f) => f)) {
        return { x: rect.left + pixel[0], y: rect.top + pixel[1] };
      }
    }
    return null;
  }, kind);
}

// Airport labels whose text box falls outside the map viewport. The labels are
// read through the layer's style function so the check tracks the real font and
// offsets instead of duplicating them.
async function clippedLabels(page) {
  return page.evaluate(() => {
    const ctx = window.__openflightsMap;
    const size = ctx.map.getSize();
    const styleFunction = ctx.airportLayer.getStyleFunction();
    const g = document.createElement("canvas").getContext("2d");
    let checked = 0;
    const clipped = [];

    ctx.airportLayerSource.getFeatures().forEach((feature) => {
      const styles = styleFunction(feature, ctx.view.getResolution());
      const list = Array.isArray(styles) ? styles : [styles];
      const text = list
        .map((style) => style && style.getText())
        .find((candidate) => candidate && candidate.getText());
      if (!text) return;

      const pixel = ctx.map.getPixelFromCoordinate(
        feature.getGeometry().getCoordinates()
      );
      // Only pins that are on screen have labels worth checking; the world
      // clamp may legitimately push outliers just outside the viewport.
      if (
        pixel[0] < 0 ||
        pixel[1] < 0 ||
        pixel[0] > size[0] ||
        pixel[1] > size[1]
      ) {
        return;
      }

      g.font = text.getFont();
      const metrics = g.measureText(text.getText());
      const ascent =
        metrics.fontBoundingBoxAscent == null
          ? 10
          : metrics.fontBoundingBoxAscent;
      const left = pixel[0] + text.getOffsetX();
      const right = left + metrics.width;
      const bottom = pixel[1] + text.getOffsetY();
      const top = bottom - (ascent + (metrics.fontBoundingBoxDescent || 0));

      checked += 1;
      if (left < 0 || top < 0 || right > size[0] || bottom > size[1]) {
        clipped.push({
          label: text.getText(),
          left: Math.round(left),
          top: Math.round(top),
          right: Math.round(right),
          bottom: Math.round(bottom),
        });
      }
    });

    return { checked, size, clipped };
  });
}

module.exports = [
  {
    name: "layout: large screens stack three panes and hide the tab bar",
    async run({ page }) {
      try {
        await enterLarge(page);
        const p = await panes(page);

        h.assertEqual(p.tabbar, "none", "the tab bar is hidden");
        h.assertEqual(p.bars.map, "flex", "map bar is visible");
        h.assertEqual(p.bars.flights, "flex", "flights bar is visible");
        h.assertEqual(p.bars.overview, "flex", "overview bar is visible");
        h.assertEqual(
          p.order.join(","),
          "map,flights,overview",
          "panes are stacked map, flights, overview"
        );
        h.assert(p.map.open, "the map pane is open by default");
        h.assert(!p.flights.open, "the flights pane starts closed");
        h.assert(!p.overview.open, "the overview pane starts closed");
        h.assert(p.map.height > 400, "the map fills the free space");
        h.assert(p.flights.height < 60, "a closed pane shows only its bar");

        // A single open pane is already full height and cannot be hidden.
        h.assert(p.buttons.map.size, "map cannot resize");
        h.assertEqual(
          p.buttons.map.state,
          "restore",
          "map is already full height"
        );
        h.assert(p.buttons.map.hide, "map cannot be hidden");
        h.assert(!p.buttons.flights.size, "flights can be opened");
        h.assertEqual(
          p.buttons.flights.state,
          "split",
          "closed panes offer split view"
        );
        h.assert(p.buttons.flights.hide, "a closed pane cannot be hidden");
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: the initial map fit leaves room for airport labels",
    async run({ page }) {
      try {
        await enterLarge(page);
        const result = await clippedLabels(page);

        h.assert(result.checked > 0, "expected labelled airports on the map");
        h.assert(
          result.clipped.length === 0,
          "labels clipped by the initial fit (viewport " +
            result.size.join("x") +
            "): " +
            JSON.stringify(result.clipped.slice(0, 5))
        );
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: the size button grows flights, then makes it full screen",
    async run({ page }) {
      try {
        await enterLarge(page);

        // Closed -> half, alongside the map.
        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(
          () =>
            document
              .getElementById("view-flights")
              .classList.contains("is-open"),
          { timeout: 10000 }
        );
        await h.sleep(200);
        const half = await panes(page);
        h.assert(half.map.open && half.flights.open, "map and flights open");
        h.assert(
          Math.abs(half.map.height - half.flights.height) <= 2,
          "open panes split the height evenly"
        );
        h.assert(
          half.mapSize && half.mapSize[1] <= half.map.height,
          "the map resized to its half"
        );
        h.assertEqual(
          half.buttons.flights.state,
          "maximize",
          "a half-height pane offers full height"
        );
        h.assert(
          !half.buttons.flights.hide,
          "a half-height pane can be hidden"
        );

        // Half -> full: the size button must grow the pane, not close it.
        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(
          () =>
            document
              .getElementById("view-map")
              .classList.contains("is-closed") &&
            document
              .getElementById("view-flights")
              .classList.contains("is-open"),
          { timeout: 10000 }
        );
        await h.sleep(200);
        const full = await panes(page);
        h.assert(full.flights.open && !full.map.open, "flights is maximised");
        h.assert(
          full.flights.height > half.flights.height + 50,
          "flights grew to full height"
        );
        h.assertEqual(full.bars.map, "flex", "the map bar stays visible");
        h.assertEqual(
          full.buttons.flights.state,
          "restore",
          "a maximised pane offers restore"
        );

        // Full -> half: the size button restores the pair.
        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(
          () =>
            document.getElementById("view-map").classList.contains("is-open"),
          { timeout: 10000 }
        );
        const restored = await panes(page);
        h.assert(
          restored.map.open && restored.flights.open,
          "the size button restores the even split"
        );

        // Half -> closed: the hide button collapses the pane.
        await h.click(page, '[data-pane-hide="flights"]');
        await page.waitForFunction(
          () =>
            document
              .getElementById("view-flights")
              .classList.contains("is-closed"),
          { timeout: 10000 }
        );
        const closed = await panes(page);
        h.assert(closed.map.open && !closed.flights.open, "flights hidden");
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: clicking a pane bar expands or collapses it",
    async run({ page }) {
      try {
        await enterLarge(page);

        // Collapsed -> half, alongside the map.
        await h.click(page, "#view-flights .pane-bar__title");
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );
        await h.sleep(200);
        let p = await panes(page);
        h.assert(p.map.open && p.flights.open, "the bar click expands flights");
        h.assert(
          Math.abs(p.map.height - p.flights.height) <= 2,
          "the bar click opens flights at half height"
        );

        // Half -> collapsed.
        await h.click(page, "#view-flights .pane-bar__title");
        await page.waitForFunction(() =>
          document
            .getElementById("view-flights")
            .classList.contains("is-closed")
        );
        p = await panes(page);
        h.assert(p.map.open && !p.flights.open, "the bar click hides flights");

        // A sole open pane ignores bar clicks.
        await h.click(page, "#view-map .pane-bar__title");
        await h.sleep(150);
        p = await panes(page);
        h.assert(p.map.open && !p.flights.open, "the sole pane stays put");

        // Buttons are excluded: hiding must not be undone by the bar handler.
        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );
        await h.click(page, '[data-pane-hide="flights"]');
        await page.waitForFunction(() =>
          document
            .getElementById("view-flights")
            .classList.contains("is-closed")
        );
        p = await panes(page);
        h.assert(!p.flights.open, "the hide button does not trigger the bar");
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: at most two panes are open, newest wins",
    async run({ page }) {
      try {
        await enterLarge(page);

        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );

        await h.click(page, '[data-pane-size="overview"]');
        await page.waitForFunction(() =>
          document.getElementById("view-overview").classList.contains("is-open")
        );
        let p = await panes(page);
        h.assert(!p.flights.open, "opening overview closed flights");
        h.assertEqual(
          [p.map.open, p.flights.open, p.overview.open].filter(Boolean).length,
          2,
          "two panes open"
        );

        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );
        p = await panes(page);
        h.assert(!p.overview.open, "opening flights closed overview");
        h.assert(p.map.open && p.flights.open, "map and flights remain open");
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: the last open pane cannot be closed",
    async run({ page }) {
      try {
        await enterLarge(page);

        h.assert(
          await page.$eval('[data-pane-hide="map"]', (el) => el.disabled),
          "hiding the only open pane is disabled"
        );

        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );

        // Leaving flights as the only open pane disables both controls.
        await h.click(page, '[data-pane-hide="map"]');
        await page.waitForFunction(() =>
          document.getElementById("view-map").classList.contains("is-closed")
        );
        const p = await panes(page);
        h.assert(p.flights.open && !p.map.open, "flights became the only pane");
        h.assert(
          await page.$eval('[data-pane-hide="flights"]', (el) => el.disabled),
          "hiding the remaining pane is disabled"
        );
        h.assert(
          await page.$eval('[data-pane-size="flights"]', (el) => el.disabled),
          "a full-height pane cannot grow further"
        );
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: full height keeps the other bars visible and restores",
    async run({ page }) {
      try {
        await enterLarge(page);
        await h.click(page, '[data-pane-size="flights"]');
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );
        await h.sleep(200);
        const before = await panes(page);

        await h.click(page, '[data-pane-size="map"]');
        await page.waitForFunction(
          () =>
            document.getElementById("view-map").classList.contains("is-open") &&
            document
              .getElementById("view-flights")
              .classList.contains("is-closed")
        );
        await h.sleep(250);
        const full = await panes(page);

        h.assert(full.map.open, "the map is open");
        h.assert(!full.flights.open && !full.overview.open, "others closed");
        h.assertEqual(
          full.bars.flights,
          "flex",
          "the flights bar stays visible"
        );
        h.assertEqual(
          full.bars.overview,
          "flex",
          "the overview bar stays visible"
        );
        h.assert(
          full.map.height > before.map.height + 50,
          "the map grew to full height"
        );
        h.assertEqual(
          await page.$eval('[data-pane-size="map"]', (el) => el.dataset.state),
          "restore",
          "the map size button offers restore"
        );

        await h.click(page, '[data-pane-size="map"]');
        await page.waitForFunction(() =>
          document.getElementById("view-flights").classList.contains("is-open")
        );
        const restored = await panes(page);
        h.assert(
          restored.map.open && restored.flights.open,
          "the previous pair is restored"
        );
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: the airport chooser anchors to the cluster",
    async run({ page }) {
      try {
        await enterLarge(page);
        const pixel = await findFeaturePixel(page, "cluster");
        h.assert(pixel, "no visible airport cluster found");

        await page.mouse.click(pixel.x, pixel.y);
        await page.waitForSelector("#airport-chooser:not([hidden])", {
          timeout: 10000,
        });
        await h.sleep(200);

        const info = await page.evaluate(() => {
          const chooser = document.getElementById("airport-chooser");
          const mapPane = document.getElementById("view-map");
          const chooserRect = chooser.getBoundingClientRect();
          const paneRect = mapPane.getBoundingClientRect();
          const backdrop = document.getElementById("chooser-backdrop");
          return {
            inOverlay: chooser.parentElement.classList.contains(
              "ol-overlay-container"
            ),
            position: getComputedStyle(chooser).position,
            backdrop: getComputedStyle(backdrop).display,
            top: Math.round(chooserRect.top),
            bottom: Math.round(chooserRect.bottom),
            paneTop: Math.round(paneRect.top),
            paneBottom: Math.round(paneRect.bottom),
          };
        });

        h.assert(info.inOverlay, "chooser lives in the map overlay container");
        h.assertEqual(info.position, "static", "chooser is not a fixed sheet");
        h.assertEqual(
          info.backdrop,
          "none",
          "no modal backdrop on large screens"
        );
        h.assert(
          info.top >= info.paneTop - 2 && info.bottom <= info.paneBottom + 2,
          "chooser is anchored inside the map pane"
        );

        await h.click(page, "#chooser-close");
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
  {
    name: "layout: below the cutoff keeps the tab bar and one view",
    async run({ page }) {
      try {
        await page.setViewport({ width: 900, height: 900 });
        await page.reload({ waitUntil: "domcontentloaded" });
        await h.waitForAppReady(page);
        await h.click(page, '.tabbar__tab[data-view="map"]');
        await page.waitForFunction(
          () =>
            !document.body.classList.contains("pane-layout") &&
            document.querySelectorAll(".view.is-active").length === 1,
          { timeout: 10000 }
        );
        const p = await panes(page);
        h.assert(p.tabbar !== "none", "the tab bar is visible");
        h.assertEqual(p.bars.map, "none", "pane bars are hidden");
        h.assertEqual(
          await page.$$eval(".view.is-active", (els) => els.length),
          1,
          "only one view is shown"
        );
      } finally {
        await page.setViewport(NARROW);
      }
    },
  },
];
