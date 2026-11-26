<?php

include_once dirname(__FILE__) . '/config.php';

/**
 * Public content pages must render their shared sidebar.
 *
 * Regression test: these pages used to depend on server-side includes (SSI),
 * which nginx does not apply to PHP responses, so the whole #sideBar block
 * silently disappeared and the raw include directives leaked into the HTML.
 */
class CheckContentPageSidebars extends WebTestCase {
    /** Responsive public content pages. */
    private const PAGES = [
        "about.php",
        "faq.php",
        "data.php",
        "donate.php",
    ];

    public function testSidebarRenders(): void {
        global $webroot;

        foreach (self::PAGES as $page) {
            $html = $this->get($webroot . $page);

            $this->assertStringNotContainsString(
                "include virtual",
                $html,
                "$page: raw SSI directive leaked into the response"
            );
            $this->assertStringContainsString(
                'id="sideBar"',
                $html,
                "$page: sidebar container"
            );
            $this->assertStringContainsString(
                "/img/openflights-logo.png",
                $html,
                "$page: sidebar navigation"
            );
            $this->assertStringContainsString(
                'class="public-page"',
                $html,
                "$page: public page body class"
            );
            $this->assertStringContainsString(
                "/css/public.css",
                $html,
                "$page: responsive stylesheet"
            );
            $this->assertStringContainsString(
                'name="viewport"',
                $html,
                "$page: mobile viewport meta"
            );
            $this->assertStringNotContainsString(
                "id='locale'",
                $html,
                "$page: language picker removed"
            );
        }
    }

    public function testRouteMapsRendersSidebar(): void {
        global $webroot;

        $html = $this->get($webroot . "html/route-maps.php");

        $this->assertStringNotContainsString(
            "include virtual",
            $html,
            "route-maps: raw SSI directive leaked into the response"
        );
        $this->assertStringContainsString(
            'id="sideBar"',
            $html,
            "route-maps: sidebar container"
        );
        $this->assertStringContainsString(
            "/img/openflights-logo.png",
            $html,
            "route-maps: sidebar navigation"
        );
    }

    public function testMissingPartialsAreSilent(): void {
        global $webroot;

        $html = $this->get($webroot . "about.php");

        // About falls back to the committed Top 10 sample when the generated
        // data/top10.dat is absent.
        $this->assertStringContainsString(
            "Registered users",
            $html,
            "About: Top 10 block"
        );
        $this->assertStringNotContainsString(
            "404 Not Found",
            $html,
            "About: server error page spliced into the document"
        );
    }
}

/**
 * The account settings page is only reachable when signed in, so it needs its
 * own check: it used to include the sidebar with relative paths from html/.
 */
class CheckSettingsPageSidebar extends WebTestCase {
    public function testSettingsRendersSidebar(): void {
        global $webroot;

        assert_login($this);

        $html = $this->get($webroot . "html/settings.php");

        $this->assertStringNotContainsString(
            "include virtual",
            $html,
            "settings: raw SSI directive leaked into the response"
        );
        $this->assertStringContainsString(
            'id="sideBar"',
            $html,
            "settings: sidebar container"
        );
        $this->assertStringContainsString(
            "/img/openflights-logo.png",
            $html,
            "settings: sidebar navigation"
        );
        $this->assertStringNotContainsString(
            "404 Not Found",
            $html,
            "settings: server error page spliced into the document"
        );
    }
}
