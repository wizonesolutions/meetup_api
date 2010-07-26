<?php
require_once(dirname(__FILE__) . '/MeetupAPIBase.php');

/**
 * MeetupAPIComments 
 * 
 * @package MeetupAPI
 * @version $id$
 * @copyright 2010 WizOne Solutions
 * @author Kevin Kaland <help [at] wizonesolutions [dot] com> 
 * @license GNU Public License Version 2.0
 */

class MeetupAPIComments extends MeetupAPIBase {
  function __construct($apiKey) {
    $this->method = 'comments';
    parent::__construct($apiKey, $this->method);
  }
}

// @todo CRIT: REMOVE THIS TESTING CODE.
require_once(dirname(__FILE__) . '/tester/krumo/class.krumo.php');
//$test_key = '336b4270111f5f4ba65156511d1a3d'; //Work
$test_key = '5b3545260134293376757d53337a60'; //Personal
$muApi = new MeetupAPIComments($test_key);
$muApi->setQuery( array('group_urlname' => 'The-Gnostic-Movement-Los-Angeles',) );
set_time_limit(0);
$muApi->setPageSize(200);
$response = $muApi->getResponse();
krumo($response);

