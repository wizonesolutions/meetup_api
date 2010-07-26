<?php
require_once(dirname(__FILE__) . '/MeetupAPIBase.php');

/**
 * MeetupAPIProfilesv2 
 * 
 * @package MeetupAPI
 * @version $id$
 * @copyright 2010 WizOne Solutions
 * @author Kevin Kaland <help [at] wizonesolutions [dot] com> 
 * @license GNU Public License Version 2.0
 */

class MeetupAPIProfilesv2 extends MeetupAPIBase {
  function __construct($apiKey) {
    $this->method = '2/profiles';
    parent::__construct($apiKey, $this->method);
  }
}

