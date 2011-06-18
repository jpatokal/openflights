<?php
include_once(dirname(__FILE__) . '/OpenFlightsSeleniumTestCase.php');

class AirportSearchTest extends OpenFlightsSeleniumTestCase
{
  public function testAnonAirportSearch()
  {
    $this->open("/html/apsearch");
    $this->assertTextPresent("Airport search");
    $this->assertNotEditable("//input[@value='Save changes']");

    $this->type("iata", "LIF");
    $this->click("//input[@value='Search']");

    $this->assertTextPresent("Lifou (LIF), New Caledonia");
    $this->click("//input[@value='Load']");

    $this->assertValue("airport", "Lifou");
  }

  public function testLoggedInAirportSearch()
  {
    global $settings;

    $this->open('/');
    $this->type('name', $settings['name']);
    $this->type('pw', $settings['password']);
    $this->click('loginbutton');

    $this->open("/html/apsearch");
    $this->assertTextPresent("Airport search");
    $this->assertEditable("//input[@value='Save changes']");
  }
}
?>
