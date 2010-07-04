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
      'xml',
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

  function setFormat($format) {
    if (in_array($format, $this->validFormats)) $this->format = $format;
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
        //TODO: This json_decode has a BIG limitation, but it is built into PHP and is thus preferred. However, I wouldn't mind offering a backup decoding function. Gotta save on that paging activity.
        $json_response = json_decode($responseData);
        return $json_response;
        break;
      case 'xml':
        $xml_response_raw = simplexml_load_string($responseData);
        //Here goes nothing
        $xml_response = $this->parseXmlIntoObject($xml_response_raw);
        return $xml_response;
        break;
      default:
        //We don't know how to execute this request, so return FALSE.
        // @todo MEDIUM: Add some sort of notification or warning to the user that this format is not yet supported.
        return FALSE;
        break;
    }
  }

  /* @todo: Taking this XML at face value is a bad idea. It would be better to hard-map the tags and data hierarchy to expect, a bit like I do in Easy Populate Converter. Then I can traverse it without recursion and get around the hideous question of, "How do I tell if a child has children itself, and if those are part of a list of itself or simply child properties?" Granted, there are few child properties that actually themselves contain multiple results. In fact, I can only think of topics. So accomodating all children isn't really needed. I can abstract this a bit and make life easier. I can refactor it later.

  THEREFORE, this XML parsing is probably not something that MeetupAPIBase should be doing at all. It doesn't have enough information about what to expect, and the whole point of this module is to present callers with nice, easy-to-use, CONSISTENT arrays of results, regardless of whether they were retrieved in JSON or XML.

  A bit to consider.
  */
  protected function parseXmlIntoObject($xmlObj, $depth = 0) {
    $xmlResult = new stdClass();
    $valueCount = count($xmlObj);
    if ($valueCount) {
      foreach($xmlObj as $key => $value) {
        if ($depth == 0 && $key == 'head') {
          //This is just to make it match the JSON. Maybe I should change them both to something else though? @todo: Decide on that.
          $useKey = 'meta';
        }
        elseif ($depth == 0 && $key == 'items') {
          $useKey = 'results'; //again, make it match the JSON
        }
        else {
          $useKey = $key;
        }
        $children = $value->children();
        if (count($children)) {
          foreach($children as $key2 => $value2) {
            $useKey2 = $key2;
            if (isset($children->{$useKey2}[1])) {
              $arrayMode = TRUE;
            }
            if ($arrayMode) {
              $xmlResult->{$useKey}[] = $this->parseXmlIntoObject($value2, $depth + 1);
            }
            else {
              $xmlResult->{$useKey}->{$useKey2} = $this->parseXmlIntoObject($value2, $depth + 1);
            }
          }
        }
        else {
          //No children, so use the value itself - put it straight into xmlResult if we're still on depth 0, otherwise return it
          if ($depth == 0) {
            $xmlResult->{$useKey} = $value;
          }
          else {
            return (string) $value;
          }
        }
        if ($depth > 0) return $xmlResult;
      }
    }
    else {
      if ($depth == 0) return FALSE;
      else return (string) $xmlObj;
    }
    if ($depth == 0) return $xmlResult;
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
$muApi->setFormat('xml');
krumo($muApi->getResults());

