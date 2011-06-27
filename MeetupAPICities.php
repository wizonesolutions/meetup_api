<?php
require_once(dirname(__FILE__) . '/MeetupAPIBase.php');

/**
 * MeetupAPICities 
 * 
 * @package MeetupAPI
 * @version $id$
 * @license GNU Public License Version 2.0
 */

class MeetupAPICities extends MeetupAPIBase {
  function __construct($apiKey) {
    $this->method = 'cities';
    parent::__construct($apiKey, $this->method);
  }
}

