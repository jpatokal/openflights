<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class WebLoginTest extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser('*firefox /Applications/Firefox.app/Contents/MacOS/firefox-bin');
    $this->setBrowserUrl('http://openflights.local/');
  }
  
  public function testFailedLoginAndCreateAccount()
  {
    $this->open('/');
    $this->type('name', 'autotest');
    $this->type('pw', 'incorrect');
    $this->click('loginbutton');
    $this->verifyTextPresent('Login failed.');

    $this->click('link=Create account');
    $this->verifyLocation('*/html/settings?new=yes');
  }

  public function testFailedLoginAndResetPassword()
  {
    $this->open('/');
    $this->type('name', 'autotest');
    $this->type('pw', 'incorrect');
    $this->click('loginbutton');
    $this->verifyTextPresent('Login failed.');

    $this->click('link=reset password');
    $this->waitForPopUp();
    $this->selectWindow('OpenFlights: Reset password');
    $this->click('close');
  }

  public function testSuccessfulLogin()
  {
    $this->open('/');
    $this->type('name', 'autotest');
    $this->type('pw', 'autotest');
    $this->click('loginbutton');
    $this->verifyTextPresent('Hi, autotest !');
  }
}
?>
