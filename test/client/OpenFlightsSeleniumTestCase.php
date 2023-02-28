<?php

use PHPUnit\Extensions\Selenium2TestCase;

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

include_once(dirname(__FILE__) . '/../server/config.php');

class OpenFlightsSeleniumTestCase extends Selenium2TestCase
{
  protected $captureScreenshotOnFailure = true;
  protected $screenshotPath = '/tmp/screenshots';
  protected $screenshotUrl = '/tmp/screenshots';

  protected function setUp(): void
  {
    $this->setBrowser('*firefox /Applications/Firefox.app/Contents/MacOS/firefox-bin');
    $this->setBrowserUrl('http://openflights.local/');
    $this->setSpeed(100);
  }
}
