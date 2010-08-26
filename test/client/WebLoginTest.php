<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

include_once(dirname(__FILE__) . '/../server/config.php');

class WebLoginTest extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser('*firefox /Applications/Firefox.app/Contents/MacOS/firefox-bin');
    $this->setBrowserUrl('http://openflights.local/');
  }
  
  public function testFailedLogin()
  {
    global $settings;

    $this->open('/');
    $this->type('name', $settings['name']);
    $this->type('pw', 'invalid');
    $this->click('loginbutton');
    $this->verifyTextPresent('Login failed.');

    $this->click('link=Create account');
    $this->verifyLocation('*/html/settings?new=yes');

    $this->open('/');
    $this->type('name', $settings['name']);
    $this->type('pw', 'invalid');
    $this->click('loginbutton');
    $this->verifyTextPresent('Login failed.');

    $this->click('link=reset password');
    $this->waitForPopUp();
    $this->selectWindow('OpenFlights: Reset password');
    $this->click('close');
  }

  public function testSuccessfulLogin()
  {
    global $settings;

    $this->open('/');
    $this->type('name', $settings['name']);
    $this->type('pw', $settings['password']);
    $this->click('loginbutton');
    $this->verifyTextPresent("Hi, ${settings['name']} !");
    $this->verifyTextPresent('1 segments');
    $this->verifyTextPresent('1000 miles');
  }
}
?>
