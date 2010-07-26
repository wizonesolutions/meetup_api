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

