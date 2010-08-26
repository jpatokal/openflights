<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

include_once(dirname(__FILE__) . '/../server/config.php');

class WebMapPublicTest extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    global $webroot;

    $this->setBrowser('*firefox /Applications/Firefox.app/Contents/MacOS/firefox-bin');
    $this->setBrowserUrl($webroot);
  }
  
  public function testPublicUserMapAsAnonymous()
  {
    global $settings;

    $this->open("/user/" . $settings['name']);
    $this->verifyTextPresent("${settings['name']}'s flights");

    # Analyze
    $this->click("//input[@value='Analyze']");
    $this->verifyTextPresent("Total flown");
    $this->verifyTextPresent("1000 mi");

    # Top 10
    $this->click("//input[@value='Top 10']");
    $this->verifyTextPresent("Lifou (LIF)");
    $this->click('link=LIF');
    $this->verifyTextPresent("Lifou, New Caledonia");
    $this->click("//img[@onclick='JavaScript:closePopup(true);']");
    $this->click('link=Decatur Aviation');
    $this->verifyTextPresent($settings['name'] . "'s flights on Decatur Aviation");

    # Back to main map
    $this->select('Airlines', 'label=All carriers');
    $this->verifyTextPresent($settings['name'] . "'s flights");
  }
}
?>
