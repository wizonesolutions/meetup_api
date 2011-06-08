<?php
require_once(dirname(__FILE__) . '/MeetupAPIBase.php');

/**
 * MeetupAPIOpenEvents 
 * 
 * @package MeetupAPI
 * @version $id$
 * @license GNU Public License Version 2.0
 */

class MeetupAPIOpenEvents extends MeetupAPIBase {
  function __construct($apiKey) {
    $this->method = '2/open_events';
    parent::__construct($apiKey, $this->method);
  }
}

