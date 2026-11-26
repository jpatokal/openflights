<?php

/**
 * Helpers for rendering site-specific partials.
 *
 * Advertising, analytics and generated datasets differ per installation, so
 * they are kept out of version control (see .gitignore and INSTALL) and may be
 * absent in a fresh checkout. Rendering them through these helpers keeps a
 * missing file quiet: no PHP warning, no server error page spliced into the
 * document, and no dependency on server-side includes.
 */

/**
 * Include a site partial if it exists.
 *
 * If the primary file is missing and a fallback is given, the fallback is used
 * instead (e.g. a committed sample of a generated dataset).
 *
 * @param string $path Absolute path to the partial.
 * @param string|null $fallback Absolute path used when $path does not exist.
 */
function site_partial(string $path, ?string $fallback = null): void {
    if (!is_file($path) && $fallback !== null) {
        $path = $fallback;
    }
    if (is_file($path)) {
        include $path;
    }
}

/**
 * Render the sidebar: shared navigation plus the deployer's ad slot.
 *
 * @param string $root Absolute path to the OpenFlights root directory.
 */
function site_sidebar(string $root): void {
    include_once $root . "/sidebar.php";
    site_partial($root . "/html/ad-sidebar.html");
}
