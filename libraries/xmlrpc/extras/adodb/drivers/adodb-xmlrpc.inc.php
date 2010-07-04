<?php
/**
 * Meta-driver for usage in conjunction with an xmlrpc-server
 *
 * @author G. Giunta
 *
 * @version   $Revision: 1.3 $ $Date: 2008/03/07 16:47:06 $ $Author: ggiunta $
 *
 * @copyright Copyright (c) 2004-2009 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license.
 *            Whenever there is any discrepancy between the two licenses,
 *            the BSD license will take precedence.
 *
 * @todo implement SetCertificate, SetSSLVerifyPeer, SetSSLVerifyHost: give full acccess to internal xmlrpc client object?
 *
 * @todo implement nconnect
 *
 * @todo implement connection using user & pwd only (must implement it first server-side...)
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
//require_once('xmlrpc.inc');
require_once('toxmlrpc.inc.php');

class ADODB_xmlrpc extends ADOConnection
{
  // FIELDS OF THE BASE CLASS WE OVERRIDE
  var $databaseType = "xmlrpc";
  var $dataProvider = "xmlrpc";
  var $_errorNo = 0;

  // EXTRA FIELDS
  //var $_server_path;
  //var $_server_hostname;
  //var $_server_port;
  var $_timeout = 0;
  var $_server_protocol = 'http';
  var $_xmlrpcclient;
  var $xmlrpcmsg_class = 'xmlrpcmsg';
  var $xmlrpcclient_class = 'xmlrpc_client';
  var $xmlrpcserver_func_prefix = 'adodbserver.';

  function ADODB_xmlrpc()
  {
  }

/* STUFF WE HAVE TO IMPLEMENT AS PER DOCUMENTATION */

  function _connect($host=null, $user=null, $password=null, $database=null, $mode=0)
  {
    if ($mode)
      $methname = 'pconnect';
    else
      $methname = 'connect';

    // DECODE $HOST
    if ($host != null)
    {
      /// decode $host into server, path, port
      if (strpos($host, "http://") !== false)
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
      }

      $this->SetProxy($path, $hostname, $port, $user, $password);
    }

    // DECODE $DATABASE
    if ($database)
    {
      // use
      require_once('adodb-pear.inc.php');
      $dsn = DB::parseDSN($database);

      if ($dsn)
        $msg =& new $this->xmlrpcmsg_class($this->xmlrpcserver_func_prefix.$methname,
            array(
              new xmlrpcval($dsn['phptype']),
              new xmlrpcval($dsn['hostspec']),
              new xmlrpcval($dsn['username']),
              new xmlrpcval($dsn['password']),
              new xmlrpcval($dsn['database'])
            )
          );
      else
        // @todo set some error state/msg: DSN parsing failed
        return false;
    }
    else
    {
      $msg =& new $this->xmlrpcmsg_class($this->xmlrpcserver_func_prefix.$methname);
    }

    $resp = $this->_send($msg, true);
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
      $resp = $resp->value();
	  if ($resp->arraysize() < 4) {
        $this->_errorNo = -2;
        $this->_errorMsg = 'Xmlrpc proxy returned unknown error response (0)';
        $this->_connectionID = null;
        return false;
	  }

      // save the connection ID we retrieved
      $connID = $resp->arraymem(2);
      $this->_connectionID = $connID->scalarval();

      // and set all of the fields of the connection object that are specified from the remote connection
      $conndesc = $resp->arraymem(3);
      while(list($key, $val) = $conndesc->structeach())
        $this->$key = $val->scalarval();

      return true;
    }
  }

  function _pconnect($host=null, $user='', $password='', $database=null)
  {
    return $this->_connect($host, $user, $password, $database, 1);
  }

  function _query($sql, $inputarr=false)
  {
    $msg =& new $this->xmlrpcmsg_class($this->xmlrpcserver_func_prefix.'execute',
    array(
      new xmlrpcval($this->_connectionID),
      new xmlrpcval($sql)
    )
    );

    if ($inputarr)
    {
      $inputs = php_xmlrpc_encode($inputarr);
      $msg->addParam($inputs);
    }

    $resp = $this->_send($msg);

    if ($resp)
    {
      // if we got a struct, it 'should' be an encoded recordset
      if ($resp->kindOf() == 'struct')
        return xmlrpcval2rs($resp);
      else
        // a single boolean of 'true' gets converted into an empty recordset,
        // same behaviour as any 'real' driver would do
        if ($resp->scalartyp() == 'boolean' && $resp->scalarval())
        {
          global $ADODB_vers;
          if ((float)substr($ADODB_vers,1) >= 4.23)
            // adodb later than 4.23 has added init() method to empty rs: noe need to do it by hand
            return new ADORecordset_empty();
          else
            return new ADORecordset_empty_I();
        }
        else
          // this case is not accounted for: just let the caller know there's something wrong
          // NB: we might set some error code here...
          return false;
    }
    else
      return false;
  }

  function _close()
  {
    $msg =& new $this->xmlrpcmsg_class($this->xmlrpcserver_func_prefix.'close',
      array(new xmlrpcval($this->_connectionID))
    );

    return $this->_send($msg);
    /*$resp =& ;
    if (!$resp)
      return false;
    else
      // NB: are we sure that the $respobject is an xmlrpcval of type array
      // and that is has exactly 2 members with appropriate names ???
      return xmlrpc_decode($resp);*/
  }

/* OTHER FUNCTIONS WE OVERRIDE 'BECAUSE WE CAN' */

  // Override errorno(): we loike to give back real error numbers, not only 1 and 0
  function ErrorNo()
  {
    return $this->_errorNo;
  }

  function ServerInfo()
  {
    $msg =& new $this->xmlrpcmsg_class($this->xmlrpcserver_func_prefix.'callconnectionfunction',
        array(new xmlrpcval($this->_connectionID), new xmlrpcval('ServerInfo'))
      );
    $resp = $this->_send($msg);
    if (!$resp)
      return array('description' => '', 'version' => '');
    else
      // NB: are we sure that the $respobject is an xmlrpcval of type array
      // and that is has exactly 2 members with appropriate names ???
      return php_xmlrpc_decode($resp);
  }

/* EXTRA STUFF: NEW PUBLIC AND PRIVATE FUNCTIONS */

  function SetProxy($server_path, $server_hostname, $server_port=null, $user=null, $password=null, $timeout=null, $server_method=null)
  {
    //$this->_server_path = $server_path;
    //$this->_server_hostname = $server_hostname;
    if ($server_port !== null)
    {
      //$this->_server_port = $server_port;
      $this->_xmlrpcclient =& new $this->xmlrpcclient_class($server_path, $server_hostname, $server_port);
    }
    else
    {
      $this->_xmlrpcclient =& new $this->xmlrpcclient_class($server_path, $server_hostname);
    }

    if ($user)
      $this->_xmlrpcclient->setCredentials($user, $password);

    if ($timeout !== null)
    {
      $this->_timeout = $timeout;
    }
    if ($server_method !== null)
    {
      $this->_server_protocol = $server_method;
    }

  }

  function _send($payload, $return_full_resp=false)
  {
    $client =& $this->_xmlrpcclient;
    if (is_object($client))
    {
      $client->setDebug($this->debug);
      $resp =& $client->send($payload, $this->_timeout, $this->_server_protocol);
      if (!$resp)
      {
        $this->_errorNo = $client->errno;
        $this->_errorMsg = $client->errstring;
        return false;
      }
      else
      {
        if ($resp->faultCode())
        {
          $this->_errorNo = $resp->faultCode();
          $this->_errorMsg = $resp->faultString();

          return false;
        }
        else
        {
//var_dump($resp);
          $xmlrpcVal = $resp->value();
          if ($xmlrpcVal->kindOf() != 'array' || $xmlrpcVal->arraysize() < 2) {
            $this->_errorNo = -2;
            $this->_errorMsg = 'Xmlrpc proxy returned bad response';
            return false;
          }
          $errorno = $xmlrpcVal->arraymem(0);
          $errorno = $errorno->scalarval();
          if ($errorno)
          {
            $this->_errorNo = $errorno;
            $errorstr = $xmlrpcVal->arraymem(1);
            $this->_errorMsg = $errorstr->scalarval();
            return false;
          }
          else
          {
            // Last but not least: RPC call was a success!!!

            // NB: shall we reset error code here ???
            if ($return_full_resp)
              return $resp;
            else
              if ($xmlrpcVal->arraysize() > 2)
                return $xmlrpcVal->arraymem(2);
              else
                return true;
          }
        }
      }
    }
    else
    {
      $this->_errorNo = -1;
      $this->_errorMsg = 'Cannot execute request: xmlrpc proxy not set';
      return false;
    }
  }

}

/**
* Recordset class that will be instantiated by ADODB when executing query of xmlrpc connection
*/
class ADORecordSet_xmlrpc
{
  /**
  * HACK HACK HACK
  * The xmlrpc connection takes care of bulding and initing the appropriate recordset,
  * and passes it to us as an object.
  * So we just copy the object reference into ourself. Lovely PHP let us do that :)
  */
  function ADORecordSet_xmlrpc($objectID, $fetchmode=null)
  {
    $this = $objectID;
    //return $this;
    $this->databaseType = 'xmlrpc';
  }
}

/*
* Empty recordset class that adds the init() method
* NB: the baseline ADORecordset_empty class has the init() method since
* at last version 4.23
*/
class ADORecordset_empty_I extends ADORecordset_empty
{
  function Init()
  {
  }
}

?>
