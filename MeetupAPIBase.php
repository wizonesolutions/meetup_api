<?php 
/**
 * @file MeetupAPIBase.php
 * @author Kevin Kaland <help [at] wizonesolutions [dot] com>
 * This file contains methods common to all client classes and serves as the base class, providing properties that child classes should override.
 */

require_once(dirname(__FILE__) . '/config.php');

/**
 * MeetupAPIBase 
 * This class contains the essential functionality to make building out the API functionality easier. It can also be implemented itself. In this case, it needs to be supplied the method for the API request.
 * @package MeetupAPIBase
 * @version $id$
 * @copyright 2010 WizOne Solutions
 * @author Kevin Kaland <help [at] wizonesolutions [dot] com> 
 * @license GNU Public License Version 2.0
 */
class MeetupAPIBase {
  protected $validFormats = array('json',
      );
  protected $apiKey, $apiUrl, $format, $method, $pageSize, $numPages, $sortDesc, $query, $curl; //This should be set to the Meetup API method implemented by the child class.

  function __construct($apiKey, $method) {
    $this->apiKey = $apiKey;
    $this->method = $method;
    $this->apiUrl = API_URL;
    $this->format = API_FORMAT;
    $this->pageSize = API_PAGE_SIZE;
    $this->numPages = API_NUM_PAGES;
    $this->sortDesc = API_SORT_DESC;
  }

  function __autoload($className) {
    require_once(dirname(__FILE__) . $className . '.php');
  }

  function setQuery($query) {
    $this->validateQuery($query);
  }

  /**
   * validateQuery 
   * In child classes, this should be overridden and strip out invalid parameters, etc. 
   * @param mixed $query 
   * @return void
   */
  protected function validateQuery($query) {
    $this->query = $query;
  }

  protected function validateFormat() {
    if (!in_array($this->format, $this->validFormats)) return FALSE;
    else return TRUE;
  }

  function getResults() {
    if (!$this->validateFormat()) return;
    $responseData = $this->request();
    if ($results = $this->parseResponse($responseData)) {
      // @todo CRIT: Implement paging here.
      return $results;
    }
    else return FALSE;
  }
  
  function parseResponse($responseData) {
    switch($this->format) {
      case 'json':
        $json_response = json_decode($responseData);
        return $json_response;
        break;
      default:
        //We don't know how to execute this request, so return FALSE.
        // @todo MEDIUM: Add some sort of notification or warning to the user that this format is not yet supported.
        return FALSE;
        break;
    }
  }

  /**
   * initRequest 
   * Wrapper function to initiate the HTTP request.
   * @return void
   */
  protected function initRequest($offset = 0) {
    //URL-encode the query
    $request_settings = array('key' => $this->apiKey,
        'page' => $this->pageSize,
        'offset' => $offset,
        'desc' => $this->sortDesc,
        );
    $request = http_build_query(array_merge($this->query, $request_settings));
    $this->curl = curl_init($this->getRequestUrl() . '?' . $request);
    curl_setopt($this->curl, CURLOPT_HEADER, FALSE);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
  }

  /**
   * execRequest 
   * 
   * @return stdClass
   */
  protected function execRequest() {
    $responseData = curl_exec($this->curl);
    return $responseData;
  }

  /**
   * closeRequest 
   * Wrapper function to close the HTTP request. 
   * @return void
   */
  protected function closeRequest() {
    curl_close($this->curl);
  }

  protected function request() {
    $this->initRequest();
    $responseData = $this->execRequest();
    $this->closeRequest();
    return $responseData;
  }

  protected function getRequestUrl() {
    return $this->apiUrl . $this->method . '.' . $this->format;
  }

  function setPageSize($pageSize) {
    if ((int) $pageSize > 0) $this->pageSize = $pageSize;
  }

  function getPageSize() {
    return $this->pageSize;
  }

  function setNumPages($numPages) {
    if (is_numeric($numPages) && (int) $numPages >= 0) $this->numPages = $numPages;
  }

  function getNumPages() {
    return $this->numPages;
  }

  function setSortDesc($sortDesc) {
    if ($sortDesc == TRUE) $this->sortDesc = $sortDesc;
    else return FALSE;
  }

  function getSortDesc() {
    return $this->sortDesc;
  }
}

// @todo CRIT: REMOVE THIS TESTING CODE.
require_once(dirname(__FILE__) . '/tester/krumo/class.krumo.php');
$test_key = '336b4270111f5f4ba65156511d1a3d';
$muApi = new MeetupAPIBase($test_key, 'groups');
$muApi->setQuery( array('zip' => '11211',
      'order' => 'ctime',) );
$muApi->setPageSize(43);
$muApi->setSortDesc(TRUE);
krumo($muApi->getResults());

