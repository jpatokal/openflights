<?php

include_once dirname(__FILE__) . '/OpenFlightsSeleniumTestCase.php';

class WebLoginTest extends OpenFlightsSeleniumTestCase {
    public function testFailedLogin() {
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

    public function testSuccessfulLogin() {
        global $settings;

        $this->open('/');
        $this->type('name', $settings['name']);
        $this->type('pw', $settings['password']);
        $this->click('loginbutton');
        sleep(1);
        $this->verifyTextPresent("Hi, ${settings['name']} !");
        $this->verifyTextPresent('1 segments');
        $this->verifyTextPresent('1000 miles');

        $this->click("//input[@value='New flight']");
        $this->verifyTextPresent("Add new flights");
    }

    public function testReload() {
        global $settings;

        $this->open('/');
        $this->type('name', $settings['name']);
        $this->type('pw', $settings['password']);
        $this->click('loginbutton');
        sleep(1);
        $this->verifyTextPresent("Hi, ${settings['name']} !");

        $this->open('/');
        $this->verifyTextPresent("Logged in as ${settings['name']}");

        $this->click("//input[@value='New flight']");
        $this->verifyTextPresent("Add new flights");
    }
}
