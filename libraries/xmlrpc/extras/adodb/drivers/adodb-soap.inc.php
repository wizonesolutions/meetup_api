<?php
/**
 * Meta-driver for usage in conjunction with a soap-server
 * 
 * @author G. Giunta
 * 
 * @version   $Revision: 1.3 $ $Date: 2008/03/07 16:47:06 $ $Author: ggiunta $
 * 
 * @copyright Copyright (c) 2005-2009 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license. 
 *            Whenever there is any discrepancy between the two licenses, 
 *            the BSD license will take precedence.
 *
 * @todo decode SOAP error codes into a single integer 
 * @todo implement correct retrieval of db connection info
 * @todo test close method
 * @todo implement 'execute generic server function' method
 * 
 * Notes:     Non-temporary connections to the database do not need to be explicilty closed.
 *            In fact trying to close them will return an error.
 * 
 *            We have a real problem with Permanent connections: once they have been open,
 *            there is no easy way to close them, since HTTP is connectionless and 
 *            server-side there is no way to serialize a resource variable (limitation of PHP),
 *            the PHP script that will be called to close the connection has no way to retrieve
 *            a connection handle from the script that opened it.
 *            The only workaround is to use a stateful web server, i.e. a (single-threaded ?) php process
 *            that does all the work of Apache, stays alive between requests by clients and can thus
 *            keep track of open db connections. To do in the future???
 **/
 
//require_once('adodb.inc.php');
//require_once('nusoap.php');
require_once('tosoap.inc.php');

class ADODB_soap extends ADOConnection
{
  // FIELDS OF THE BASE CLASS WE OVERRIDE
  var $databaseType = "soap";
  var $dataProvider = "soap";
  var $_errorNo = 0;

  // EXTRA FIELDS
  //var $_server_path;
  //var $_server_hostname;
  //var $_server_port;
  //var $_timeout = 0;
  //var $_server_protocol = 'http';
  var $_soapclient;
  var $soapmsg_class = 'soapval';
  var $soapclient_class = 'soapclient';
  var $soapserver_func_prefix = '';
  var $fix_query = false;

  function ADODB_soap()
  {
    // Check for NUSOAP versions that do not XML escape element attribute values
    $a = new nusoap_base;
    if (version_compare($a->version, '0.7') < 0)
      $this->fix_query = true;
  }

/* STUFF WE HAVE TO IMPLEMENT AS PER DOCUMENTATION */

  function _connect($host=null, $user=null, $password=null, $database=null, $mode=0)
  {
    global $tosoap_namespace;

    if ($mode)
      $methname = 'pconnect';
    else
      $methname = 'connect';
    
    // DECODE $HOST
    if ($host != null)
    {
      /// decode $host into server, path, port
      /*if (strpos($host, "http://") !== false)
        $host = substr($host, 7);
      elseif (strpos($host, "https://") !== false)
        $host = substr($host, 8);

      $pos = strpos($host, "/");
      if ($pos !== false)
      {
        $hostname = substr($host, 0, $pos);
        $path = substr($host, $pos);
        if ($path == "/")
          $path = "/";
      }
      else
      {
        $hostname = $host;
        $path = "/";
      }

      if (strpos($hostname, ":") !== false)
      {
        $hostinfo = explode(":", $hostname);
        $hostname = $hostinfo[0];
        $port = $hostinfo[1];
      } else {
        $port = null;
      }*/

      $this->SetProxy($host, $user, $password);
    }

    // DECODE $DATABASE
    if ($database)
    {
      // use 
      require_once('adodb-pear.inc.php');
      $dsn =& DB::parseDSN($database);

      if ($dsn)
      {
//timenow('build_connect_soapval_start');
        $msg =& new soapval('connectionParams', 'ConnectionParams', null, 
          null, $tosoap_namespace,
            array(
              //'' => $dsn['phptype'],
              'host' => $dsn['hostspec'],
              'user' => $dsn['username'],
              'password' => $dsn['password'],
              'database' => $dsn['database']
            )
          );
//timenow('build_connect_soapval');
        $msg = array($msg);
      }
      else
        // @todo set some error state/msg: DSN parsing failed
        return false;
    }
    else
    {
      $msg = '';
    }

//timenow('send_connect_soapval_start');
    $resp = $this->_send($this->soapserver_func_prefix.$methname, $msg);
//timenow('send_connect_soapval');
    if (!$resp)
    {
      $this->_connectionID = null;
      return false;
    }
    else
    {

//var_dump($resp);
      // make sure that we got a valid response.
	  // NB: some checking is made inside _send: response is an array w. at least 2 members,
	  // but a valid connect call will return at least 4 members      
	  if (count($resp) < 3 || !isset($resp['!connectionID'])) {
        $this->_errorNo = -2;
        $this->_errorMsg = 'soap proxy returned unknown error response (0)';
        $this->_connectionID = null;
        return false;
	  }

      // save the connection ID we retrieved
      $this->_connectionID= $resp['!connectionID'];

      // and set all of the fields of the connection object that are specified from the remote connection
      /*
      if (isset($resp['@todo'])) 
      while(list($key, $val) = each($resp['@todo']))
        $this->$key = $val->scalarval();
      */
      return true;
    }
  }
  
  function _pconnect($host=null, $user='', $password='', $database=null)
  {
    return $this->_connect($host, $user, $password, $database, 1);
  }

  function _query($sql, $inputarr=false)
  {
    global $tosoap_namespace;

//echo "entering _query, $sql\n";

//timenow('build_query_soapval_start');
    if ($this->fix_query)
      $msg =& new soapval( 'query', 'Query', null, null, $tosoap_namespace,
        array(
          'connectionID' => $this->_connectionID,
          'sqlString' => htmlspecialchars($sql),  // SOAP DOES NOT HANDLE IT!!!
          //'firstRecord' => -1,
          //'maxRecords' => -1
        )
      );
    else
      $msg =& new soapval( 'query', 'Query', null, null, $tosoap_namespace,
        array(
          'connectionID' => $this->_connectionID,
          'sqlString' => $sql,
          //'firstRecord' => -1,
          //'maxRecords' => -1
        )
      );    
//timenow('build_query_soapval');
    $msg = array($msg);
// test
//$msg = '<query connectionID="DEFAULT" sqlString="select * from tsys..piazzole" firstRecord="-1" maxRecords="-1"/>';

    /*if ($inputarr)
    {
      $inputs =& soap_encode($inputarr);
      $msg->addParam($inputs);
    } */
//timenow('send_query_soapval_start');
    $resp = $this->_send($this->soapserver_func_prefix.'execSql', $msg, 'executionData');
//timenow('send_query_soapval');
    if ($resp)
    {
      // save the REAL SQL we executed: USELESS, since the parent ADODB class
      // will override it...
      //if (isset($resp['executionData']['!sqlString']))
      //{
      //  $this->sql = $resp['executionData']['!sqlString'];
      //}
      // if we got a struct, it 'should' be an encoded recordset
      if (isset($resp['resultSet']))
      {
//echo "converting to RS\n";
        return soapval2rs($resp['resultSet']);
      }
      else
      /*  // a single boolean of 'true' gets converted into an empty recordset,
        // same behaviour as any 'real' driver would do
        if ($resp->scalartyp() == 'boolean' && $resp->scalarval())
          return new ADORecordset_empty_I();
        else
          // this case is not accounted for: just let the caller know there's something wrong
          // NB: we might set some error code here...
          return false;*/
      {
            // xml we got is unexpected
            $this->_errorNo = -2;
            $this->_errorMsg = 'soap proxy returned bad response: resultset element missing from query response';
            return false;            
      }
    }
    else
      return false;
  }

  function _close()  
  {
    global $tosoap_namespace;

//timenow('build_close_soapval_start');
    $msg =& new soapval('connectionID', 'ConnectionID', null,
       null, $tosoap_namespace,
       array( 'connectionID' => $this->_connectionID));
//timenow('build_close_soapval');
    $msg = array($msg);
              //'' => $dsn['phptype'],
//timenow('send_close_soapval_start');
    $resp = $this->_send($this->soapserver_func_prefix.'close', $msg);
//timenow('send_close_soapval');
    if ($resp)
    {
      // shall we verify something other that returnCode (which has already been checked) here?
	  /*if (count($resp) < 3 || !isset($resp['!connectionID'])) {
        $this->_errorNo = -2;
        $this->_errorMsg = 'soap proxy returned unknown error response (0)';
        $this->_connectionID = null;
        return false;
	  }
      else*/
      return true;
    }
    else
      return false;
  }

/* OTHER FUNCTIONS WE OVERRIDE 'BECAUSE WE CAN' */

  // Override errorno(): we like to give back real error numbers, not only 1 and 0
  function ErrorNo()
  {
    return $this->_errorNo;
  }

/*
  function ServerInfo()
  {
    $msg =& new $this->soapmsg_class( 
        array(new soapval($this->_connectionID), new soapval('ServerInfo'))
      );
    $resp = $this->_send($this->soapserver_func_prefix.'callconnectionfunction', $msg, 'connectionData');
    if (!$resp)
      return array('description' => '', 'version' => '');
    else
      // NB: are we sure that the $respobject is an soapval of type array
      // and that is has exactly 2 members with appropriate names ???
      return soap_decode($resp);
  }
*/

/* EXTRA STUFF: NEW PUBLIC AND PRIVATE FUNCTIONS */

  function SetProxy($host, $user=null, $password=null, $timeout=null)
  {
    //$this->_server_path = $server_path;
    //$this->_server_hostname = $server_hostname;

    //if ($server_port !== null)
    //  $server_port = ':'.server_port;

    if ($timeout !== null)
    {
      //$this->_server_port = $server_port;
      $this->_soapclient =& new $this->soapclient_class($host,
        false, false, false, false, false, $timeout, $timeout);
    }
    else
    {
      $this->_soapclient =& new $this->soapclient_class($host);
    }

    if ($user)
      $this->_soapclient->setCredentials($user, $password);

  }

  function _send($method, $payload, $resp_code_member=null)
  {
    global $tosoap_namespace;
//echo "entering _send, $method\n";

    $client =& $this->_soapclient;
    if (is_object($client))
    {
      //$client->setDebug($this->debug);
      // do NOT let the client debug log grow infinitely!
      $client->clearDebug();
      $resp = $client->call($method, $payload, $tosoap_namespace, null, null, null,
        'rpc', 'literal');
//echo "soapclient->call went OK\n";
      //$resp =& $client->call($method, $payload, $tosoap_namespace);
/*
// Display the request and response
echo '<h2>Request</h2>';
echo '<pre>' . htmlspecialchars($client->request, ENT_QUOTES) . '</pre>';
echo '<h2>Response</h2>';
echo '<pre>' . htmlspecialchars($client->response, ENT_QUOTES) . '</pre>';
//echo '<h2>Decoded response</h2><pre>';
//print_r($resp);
//echo '</pre>';
// Display the debug messages
echo '<h2>Debug</h2>';
echo '<pre>' . htmlspecialchars($client->debug_str, ENT_QUOTES) . '</pre>';
*/

      if ($client->fault)
      {
        // we got a SOAP FAULT
        $this->_errorNo = $resp['faultcode'];
        $this->_errorMsg = $resp['faultstring'];
        return false;
      }
      else
      {
        // someting gone wrong inside client
        $err = $client->getError();
        if ($err)
        {
          $this->_errorNo = -3;
          $this->_errorMsg = $err;
          return false;
        }
        else
        {
//echo "OK till here, exemining member $resp_code_member";
          // 'shifto' dentro alla risposta anadndo a cercare il membro contenente il retcode
          if ($resp_code_member)
            $resp_array = isset($resp[$resp_code_member]) ? $resp[$resp_code_member] : null;
          else
            $resp_array = $resp;
 
          if (!$resp_array || count($resp_array) < 2 || !isset($resp_array['!returnCode'])) {
            // xml we got is unexpected
            $this->_errorNo = -2;
            $this->_errorMsg = 'soap proxy returned bad response';
            return false;
          }
          if ($resp_array['!returnCode'])
          {
            $this->_errorNo = $resp_array['!returnCode'];
            $this->_errorMsg = $resp_array['!returnMessage'];
            return false;
          }
          else
          {
            // Last but not least: RPC call was a success!!!

            // NB: shall we reset error code here ???
            //if ($return_full_resp)
//echo "returning from _send";
              return $resp;
            //else
            //  if ($soapVal->arraysize() > 2)
            //    return $soapVal->arraymem(2);
            //  else
            //    return true;
          }
        }
      }
    }
    else
    {
      $this->_errorNo = -1;
      $this->_errorMsg = 'Cannot execute request: soap proxy not set';
      return false;
    }
  }

}

/**
* Recordset class that will be instantiated by ADODB when executing query of soap connection
*/
class ADORecordSet_soap
{
  /**
  * HACK HACK HACK
  * The soap connection takes care of bulding and initing the appropriate recordset, 
  * and passes it to us as an object.
  * So we just copy the object reference into ourself. Lovely PHP 4 let us do that :)
  */
  function ADORecordSet_soap($objectID, $fetchmode=null)
  {
    $this = $objectID;
    //return $this;
    $this->databaseType = 'soap';
  }
}

/*
* Empty recordset class that adds the init() method
*/
class ADORecordset_empty_I extends ADORecordset_empty
{
  function Init()
  {
  }
}

?>