<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

include_once(dirname(__FILE__) . '/../server/config.php');

class OpenFlightsSeleniumTestCase extends PHPUnit_Extensions_SeleniumTestCase
{
  protected $captureScreenshotOnFailure = TRUE;
  protected $screenshotPath = '/tmp/screenshots';
  protected $screenshotUrl = 'http://localhost/screenshots';

  protected function setUp()
  {
    $this->setBrowser('*firefox /Applications/Firefox.app/Contents/MacOS/firefox-bin');
    $this->setBrowserUrl('http://openflights.local/');
    $this->setSpeed(100);
  }
}
?>
