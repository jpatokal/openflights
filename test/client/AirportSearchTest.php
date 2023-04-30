<?php
include_once(dirname(__FILE__) . '/OpenFlightsSeleniumTestCase.php');

class AirportSearchTest extends OpenFlightsSeleniumTestCase
{
    public function testAnonAirportSearch()
    {
        global $airport;

        $this->open("/html/apsearch");
        $this->assertTextPresent("Airport search");
        $this->assertTextPresent("Please log in to enable editing.");
        $this->assertNotEditable("//input[@value='Save changes']");

        $this->type("iata", $airport["iata"]);
        $this->click("//input[@value='Search']");

        $this->assertTextPresent($airport['name']);
        $this->click("//input[@value='Load']");

        $this->assertValue("airport", $airport['name']);
    }

    public function testAnonAirportSearchPreload()
    {
        global $airport;

        $apid = db_apid(db_connect());

        $this->open("/html/apsearch?apid=" . $apid);
        $this->assertTextPresent("Airport search");
        $this->assertTextPresent("Please log in to enable editing.");
        $this->assertNotEditable("//input[@value='Save changes']");
        sleep(1);
        $this->assertTextNotPresent("Loading...");
        $this->assertValue("iata", $airport['iata']);
        $this->assertValue("apid", $apid);

        $this->open("/html/apsearch?apid=garbage");
        sleep(1);
        $this->assertTextNotPresent("Loading...");
        $this->assertValue("iata", "");
    }

    public function testLoggedInAirportEdit()
    {
        global $settings, $airport;

        $this->open('/');
        $this->type('name', $settings['name']);
        $this->type('pw', $settings['password']);
        $this->click('loginbutton');
        sleep(1);

        $this->click("//input[@value='New flight']");
        $this->click("css=#row1 > td > img[title=Airport search]");

        $this->selectWindow('OpenFlights: Airport search');
        $this->assertNotEditable("//input[@value='Save changes']");

        $this->type("iata", $airport["iata"]);
        $this->click("//input[@value='Search']");

        $this->assertTextPresent($airport['name']);
        $this->click("//input[@value='Edit']");

        $this->assertValue("airport", $airport['name']);
        $this->assertNotEditable("//input[@value='Save changes']");

        $this->focus("airport");
        $this->type("airport", "EditedName");
        $this->assertEditable("//input[@value='Save changes']");

        $this->click("//input[@value='Save changes']");

        $this->selectWindow();
        $this->assertValue("src_ap1", "Testville-EditedName");
    }
}
