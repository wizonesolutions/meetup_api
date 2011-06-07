<?php 
/**
 * @file MeetupAPIBase.php
 * @author Kevin Kaland <help [at] wizonesolutions [dot] com>
 * This file contains methods common to all client classes and serves as the base class, providing properties that child classes should override.
 */

/**
 * MeetupAPIBase 
 * This class contains the essential functionality to make building out the API functionality easier. It can also be implemented itself. In this case, it needs to be supplied the method for the API request.
 * @package MeetupAPI
 * @version $id$
 * @copyright 2010 WizOne Solutions
 * @author Kevin Kaland <help [at] wizonesolutions [dot] com> 
 * @license GNU Public License Version 2.0
 */
class MeetupAPIBase {
  protected $validFormats = array('json',
      'json-alt',
      );
  protected $apiKey, $apiUrl, $format, $method, $pageSize, $numPages, $sortDesc, $query, $curl; //This should be set to the Meetup API method implemented by the child class.

  function __construct($apiKey, $method) {
    require_once(dirname(__FILE__) . '/config.php');

    $this->apiKey = $apiKey;
    $this->method = $method;
    $this->apiUrl = API_URL;
    $this->format = API_FORMAT;
    if ($this->format == 'json') {
      //Choose the (hopefully) best json library available based on the PHP version
      if (version_compare(phpversion(), '5.3') >= 0) {
        //Do nothing
      }
      else {
        //Use the JSON functionality that the XML-RPC library provides
        $this->format = 'json-alt';
      }
    }
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

  function getResponse($offset = 0, $responseData = NULL) {
    if (!$this->validateFormat()) return FALSE;
    if (!isset($responseData)) {
      $responseData = $this->request($offset);
    }
    if ($pageResponse = $response = $this->parseResponse($responseData)) {
      $pagesRead = 1;
      $currOffset = $offset;
      //The Meetup API gives us a handy, dandy next link! Let's use that! I feel like I'm using linked lists again!
      $done = FALSE;
      while ($done != TRUE) {
        $nextRequestUrl = $this->getNextRequestUrl($pageResponse);
        //Are we out of stuff we need to fetch?
        if ( ($pagesRead == $this->numPages) || empty($nextRequestUrl) || ($this->pageSize * ($currOffset + 1)) > $this->getMeta($pageResponse, 'total_count') )  {
          $done = TRUE;
          continue;
        }
        else {
          //Keep going
          $pageResponseData = $this->request(0, $nextRequestUrl); //Wonder if I'll ever actually use the "offset" feature of MeetupAPIBase::request...?
          //Overwrite the metadata and merge the results
          if (!$pageResponse = $this->parseResponse($pageResponseData)) {
            break; //Exit cleanly (I hope) if an unexpected problem occurs
          }
          $response->meta = $pageResponse->meta;
          $response->results = array_merge($response->results, $pageResponse->results);
          $pagesRead++;
          $currOffset++;
        }
      }
      return $response;
    }
    else return FALSE;
  }

  function getRawResponse($offset = 0) {
    if (!$this->validateFormat()) return FALSE;
    return $this->request($offset);
  }

  protected function getMeta($response, $field) {
    switch($this->format) {
      case 'json':
      case 'json-alt':
        return $response->meta->$field;
        break;
      default:
        return FALSE;
        break;
    }
  }

  protected function getPagingRequest($response, $direction = 'next') {
    return $this->getMeta($response, $direction);
  }

  protected function getNextRequestUrl($response) {
    return $this->getPagingRequest($response);
  }
  
  protected function getPrevRequestUrl($response) {
    return $this->getPagingRequest($response, 'prev');
  }

  function parseResponse($responseData) {
    switch($this->format) {
      case 'json':
        //This json_decode has BIG limitations in PHP 5.2, but it is built into PHP and is thus preferred if a decent one is available. json-alt is chosen if the PHP version isn't at least 5.3.
        $jsonResponse = json_decode($responseData);
        return $jsonResponse;
        break;
      case 'json-alt':
        require_once(dirname(__FILE__) . '/libraries/xmlrpc/lib/xmlrpc.inc');
        require_once(dirname(__FILE__) . '/libraries/xmlrpc/extras/jsonrpc/jsonrpc.inc');
        require_once(dirname(__FILE__) . '/libraries/xmlrpc/extras/jsonrpc/json_extension_api.inc');
        $jp = 'json_';
        if (in_array('json', get_loaded_extensions())) {
          $jp = 'json_alt_';
        }
        $jsonDecode = $jp . 'decode';
        $jsonLastError = $jp . 'last_error'; //TODO: Actually handle this
        $jsonResponse = $jsonDecode($responseData, FALSE, 1);
        // @todo MEDIUM: Do something with this to provide error messages, at least for JSON
        /* switch(json_alt_last_error()) {
         case JSON_ALT_ERROR_DEPTH:
           echo ' - Maximum stack depth exceeded';
         break;
         case JSON_ALT_ERROR_CTRL_CHAR:
           echo ' - Unexpected control character found';
         break;
         case JSON_ALT_ERROR_SYNTAX:
           echo ' - Syntax error, malformed JSON';
         break;
         case JSON_ALT_ERROR_NONE:
           echo ' - No errors';
         } */
        return $jsonResponse;
        break;
      case 'xml':
        // @todo MEDIUM: Fix problems with XML parsing...disabling for now
        /*$xml_response_raw = simplexml_load_string($responseData);
        //Here goes nothing
        $xml_response = $this->parseXmlIntoObject($xml_response_raw);
        return $xml_response;*/
        break;
      default:
        //We don't know how to execute this request, so return FALSE.
        // @todo MEDIUM: Add some sort of notification or warning to the user that this format is not yet supported.
        return FALSE;
        break;
    }
  }

  /* @todo LOW: Taking this XML at face value is a bad idea. It would be better to hard-map the tags and data hierarchy to expect, a bit like I do in Easy Populate Converter. Then I can traverse it without recursion and get around the hideous question of, "How do I tell if a child has children itself, and if those are part of a list of itself or simply child properties?" Granted, there are few child properties that actually themselves contain multiple results. In fact, I can only think of topics. So accomodating all children isn't really needed. I can abstract this a bit and make life easier. I can refactor it later.

  THEREFORE, this XML parsing is probably not something that MeetupAPIBase should be doing at all. It might work, though...need to play with it a bit more.

  A bit to consider.
  */
  protected function parseXmlIntoObject($xmlObj, $depth = 0) {
    $xmlResult = new stdClass();
    $valueCount = count($xmlObj);
    if ($valueCount) {
      foreach($xmlObj as $key => $value) {
        $useKey = $key;
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
  protected function initRequest($offset = 0, $request = NULL) {
    if (!isset($request)) {
      //URL-encode the query
      $request_settings = array('key' => $this->apiKey,
        'page' => $this->pageSize,
        'offset' => $offset,
        'desc' => $this->sortDesc,
      );
      if ($this->sortDesc == NULL) unset($request_settings['desc']); // Don't put desc in the query string at all if it isn't being used; its very presence sorts results descending!
      $request = $this->getRequestUrl() . '?' . http_build_query(array_merge($this->query, $request_settings));
    }
    $this->curl = curl_init($request);
    curl_setopt($this->curl, CURLOPT_HEADER, FALSE);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
  }

  /**
   * execRequest 
   * 
   * @return stdClass
   */
  protected function execRequest() {
    // json_decode cannot handle very large numbers. So the UTC time is shortened. 
    $responseData = preg_replace("/(\d{10})000,/",'$1,',curl_exec($this->curl));
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

  protected function request($offset = 0, $request = NULL) {
    $this->initRequest($offset, $request);
    $responseData = $this->execRequest();
    $this->closeRequest();
    return $responseData;
  }

  protected function getRequestUrl() {
    return $this->apiUrl . $this->method . '.' . $this->formatRequestName($this->format);
  }

  /**
   * formatRequestName 
   * When various implementations of a format exist, this helper function ensures the right name is still passed to the API. 
   * @param mixed $formatName 
   */
  protected function formatRequestName($formatName) {
    switch($formatName) {
      case 'json-alt':
        return 'json';
      default:
        return $formatName;
    }
  }

  function setPageSize($pageSize) {
    if ((int) $pageSize > -1) $this->pageSize = (int) $pageSize;
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
    if ($sortDesc == TRUE) $this->sortDesc = 'true';
    else $this->sortDesc = NULL;
  }

  function getSortDesc() {
    return $this->sortDesc;
  }
}

