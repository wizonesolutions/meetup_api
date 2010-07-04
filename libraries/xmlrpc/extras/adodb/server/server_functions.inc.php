<?php
/**
 * Basic xmlrpc adodb proxy: declaration and implementation of exposed functions
 *
 * @author G. Giunta
 *
 * @version   $Revision: 1.4 $ $Date: 2008/03/07 16:47:06 $ $Author: ggiunta $
 *
 * @copyright Copyright (c) 2004-2009 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license.
 *            Whenever there is any discrepancy between the two licenses,
 *            the BSD license will take precedence.
 *
 * @todo parseDSN always returns an array. Shall we impose further restrictions on
 *       the DSN parts that are MANDATORY ?
 *
 * @todo add support for specifying wheter a call to 'execute' will generate
 *       a 'Connect' or 'Pconnect' - server-wide, connection-wide or in execute params???
 *
 * @todo implement error catching on calling of user-specified adoconnection methods
 *
 * @todo finish implementing execute and selectlimit with bind params
 *
 * Notes:     The close() call will have success IFF called in the same script as the connect() call.
 *            The only way to achieve this is by using system.multicall().
 *
 *            One problem persists: how to remotely close persistent connections???
 *            Quick'n'dirty hack: terminate all available apache child processes!!!
 **/

// ****************************************************************************
// All configuration is normally done in an external file.

// The same applies for the 'main' xmlrpc server page.
// This means this page expects to be included by another one
// that will take care of building the xmlrpc server and service requests


// Little check before we go any further: just make sure all vars we DO need are set
global $adodbserver_acceptdb;
if (!isset($adodbserver_acceptdb))
  $adodbserver_acceptdb = array();

// ****************************************************************************
// Required library files before we go any futher

require_once('xmlrpc.inc');
require_once('xmlrpcs.inc');

require_once('adodb.inc.php');
require_once('toxmlrpc.inc.php');


// ****************************************************************************
/// Wrapper function used to adapt ADODB OUTP calling convention to xmlrpc server DEBUGMSG calling convention.
/// This allows us to route adodb error (debug) messages into the xmlrpc response (debug) comments
function xmlrpc_adodb_proxy_outp($msg, $newline=true)
{
  xmlrpc_debugmsg($msg);
}

// ****************************************************************************
/**
*  Define all the available xmlrpc functions here,
*  including naming and calling spec plus brief doc
*/
global $adodbserver_functionarray;
$adodbserver_functionarray = array(

  'adodbserver.pconnect' => array(
    'function' => 'adodbserver_pconnect',
    'signature' => array(
       array($xmlrpcArray),
       array($xmlrpcArray, $xmlrpcString),
       array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)
     ),
    'docstring' => "Opens a permanent connection to the specified DB. Takes same input parameters as ADODB PConnect plus driver type. Inputs:\n 1-driver\n2-host\n3-uid\n4-pwd\n5-database.\nReturns: error code and description plus (in case of success) unique id of the db connection"
  ),

  'adodbserver.connect' => array(
    'function' => 'adodbserver_connect',
    'signature' => array(
       array($xmlrpcArray),
       array($xmlrpcArray, $xmlrpcString),
       array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)
     ),
    'docstring' => "Opens a connection to the specified DB. Takes same input parameters as ADODB Connect plus driver type. Inputs:\n 1-driver\n2-host\n3-uid\n4-pwd\n5-database.\nReturns: error code and description plus (in case of success) unique id of the db connection"
  ),

  'adodbserver.close' => array(
    'function' => 'adodbserver_close',
    'signature' => array(
      array($xmlrpcArray),
      array($xmlrpcArray, $xmlrpcString)
    ),
    'docstring' => "Closes a (previously opened) permanent connection to the specified DB. Inputs:\n 1-cid.\nReturns: error code and description (0 in case of success)"
  ),

  'adodbserver.execute' => array(
    'function' => 'adodbserver_execute',
    'signature' => array(
      array($xmlrpcArray, $xmlrpcString),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcString),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcArray),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcArray)
    ),
    'docstring' => "Executes a sql statement on a given database connection (identified by CID). If db is not specified, will use default db. Inputs:\n1-cid (optional)\n2-sql\n3-array of bind params (IN binding olny allowed - optional)\nRetuns: error code, string and (in case of success) rercordset struct"
  ),

  'adodbserver.selectlimit' => array(
    'function' => 'adodbserver_selectlimit',
    'signature' => array(
      array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcInt, $xmlrpcInt)
    ),
    'docstring' => "Executes a query on a given database connection (identified by CID). If db is not specified, will use default db. Inputs:\n1-cid (optional)\n2-sql\n3-nrows\n4-offset (optional)\nRetuns: error code, string and rercordset struct"
  ),

  'adodbserver.callconnectionfunction' => array(
    'function' => 'adodbserver_callconnectionfunction',
    'signature' => array(
      array($xmlrpcArray, $xmlrpcString, $xmlrpcString, $xmlrpcArray),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcArray),
      array($xmlrpcArray, $xmlrpcString, $xmlrpcString),
      array($xmlrpcArray, $xmlrpcString)
    ),
    'docstring' => "Executes (almost) any function on a given database connection (identified by CID).  Inputs:\n1-cid (optional)\n2-function name\n3-xmlxrpc array of function parameters\n Retuns: error code, string and xmlrpc encoded return value of the called function"
  ),

  'adodbserver.getconnectioncapabilities' => array(
    'function' => 'adodbserver_capabilities',
    'signature' => array(
      array($xmlrpcArray),
      array($xmlrpcArray, $xmlrpcString)
    ),
    'docstring' => "Returns list of server capabilities.  Inputs:\n1-cid (optional)Retuns: error code, string and list of connection fields (as a struct)"
  ),

  'adodbserver.selectcsv' => array(
    'function' => 'adodbserver_selectcsv',
    'signature' => array(
      array($xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString, $xmlrpcString)
    ),
    'docstring' => "Executes a sql statement on a given database connection (identified by CID). Inputs:\n1-cid\n2-sql\n3-field delimiter\n4-record delimiter\nRetuns: (in case of success) rercordset as CSV string, else throws an xmlrpc error"
  )
)
;


// ****************************************************************************
// Below here implement all helper functions, plus add internal usage variables


/// array of custom error codes / strings
$adodbserver_errors = array (
  'PCONN_OK' => array(0, "connection opened succesfully"),
  'CLOSE_OK' => array(0, "connection closed succesfully"),
  'NO_DRIVER' => array( -998, "could not load the database driver for "),
  'DB_BLOCKED' => array( -9980, "connection to this database is not allowed"),
  'INVALID_CID' => array( -9981, "invalid connection identifier given: "),
  'NO_DEF_CID' => array( -9982, "server does not support default database connection, please connect to specific database first"),
  'NOT_CONNECTED' => array( -9983, "connection to the database not open, please connect to the database first"),
  'SQL_OK' => array(0, "query executed succesfully"),
  'NO_DEF_METH' => array(-9984, "adodb connection object does not support method: "),
  'METH_OK' => array(0, "adodb connection method executed succesfully"),
  'TOBEDONE' => array(-9985, "functionality not yet implemented in the server"),
  'UNKNOWN' => array(-9999, "undocumented error in the server"),
  'UNKNOWN_OBJ' => array(-1, "exec returned an unexpected type of value"),
);

/// array where we store all defined/open db connections
$adodbserver_connectionarray = array();

// If there is a list of connections allowed, let's save their id and connectio specification
foreach ($adodbserver_acceptdb as $adodbserver_connectionkey => $adodbserver_connectionspec)
{
  $cid = getconnectionid($adodbserver_connectionspec);
  $adodbserver_connectionarray[$cid] = array(
    'adoconnobj' => null,
    'open' => false,
    'connspeckey' => $adodbserver_connectionkey,
    'connspec' => $adodbserver_connectionspec);
}


/// Creates a unique ID for any given connection description
function getconnectionid($connectionspec, $style='DSN')
{
  if (is_array($connectionspec)) {
    if ($style == 'MD5')
      $string = md5($connectionspec['driver'].$connectionspec['host'].
        $connectionspec['uid'].$connectionspec['pwd'].$connectionspec['database']).$adodbserver_magic;
    else
    {
	  global $adodbserver_magic;
	  $string = $connectionspec['driver'].'://';
	  if ($connectionspec['uid'])
      {
        if ($connectionspec['uid'])
        $string .= $connectionspec['uid'].':'.$connectionspec['pwd'].'@';
        else
          $string .= $connectionspec['uid'].'@';
      }
      $string .= $connectionspec['host'];
	  if ($connectionspec['database'])
	    $string .= '/'.$connectionspec['database'];
      if (function_exists('mcrypt_encrypt')) {
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$string = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $adodbserver_magic, $string, MCRYPT_MODE_CBC, $iv);
        $string = base64_encode($string);
      }
    }

    return $string;
  } else
    return null;
}

/// recreates the connection description from the CID
function decodeconnectionid($connectionspec, $style='DSN')
{
  if ($style == 'MD5')
    return null;
  else
  {
    if (function_exists('mcrypt_decrypt')) {
	  global $adodbserver_magic;
      $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
      $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
      $string = base64_decode($connectionspec);
      $connectionspec = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $adodbserver_magic, $string, MCRYPT_MODE_CBC, $iv);
    }
	require_once('adodb-pear.inc.php');
    $dsn =& DB::parseDSN($connectionspec);
    if (is_array($dsn))
      return array(
        'driver' => $dsn['phptype'],
        'host' => $dsn['hostspec'],
    	'uid' => $dsn['username'],
    	'pwd' => $dsn['password'],
    	'database' => $dsn['database']
	  );
	else
	  return false;
  }
}

/// Validate caller IP against allowed list of callers
function validatecaller($remote)
{
  global $adodbserver_acceptip;

  if (!empty($adodbserver_acceptip)) {
    if (isset($HTTP_SERVER_VARS["REMOTE_ADDR"]))
      $remote = $HTTP_SERVER_VARS["REMOTE_ADDR"];
    else
      $remote = $_SERVER["REMOTE_ADDR"];
    if ($remote == '127.0.0.1' || $remote == 'localhost')
      return true;
    else {
      if (is_array($adodbserver_acceptip)) {
        foreach ($adodbserver_acceptip as $val)
          if ($remote == $val)
            return true;
        return false;
      } else
        return ($remote == $adodbserver_acceptip);
    }
  } else
    return true;
};

/// Given a connection descriptor, checks if it's allowed, and returns either an error xmlrpcresp or the corresponding db connection ID
function validatecid($connid)
{
  global $adodbserver_acceptanydb, $adodbserver_connectionarray;

  if (empty($connid)) {
    // no connection specified: try to use default connection
    foreach ($adodbserver_connectionarray as $key => $val)
    {
      if ($val['connspeckey'] == 'default')
      //$connid = getconnectionid($adodbserver_acceptdb['default']);
      //return $connid;
        return $key;
    }
    //} else {
    return stderrorresponse('NO_DEF_CID');
    //}
  } else
    // shall we accept any cid???
    if ($adodbserver_acceptanydb) {
      return $connid;
    }
    else
      if (array_key_exists($connid, $adodbserver_connectionarray)) {
        //return $adodbserver_connectionarray[$connid]['connspeckey'];
        return $connid;
      } else {
        return stderrorresponse('INVALID_CID', $connid);
      }
}

/// Given a connection CID or description, tries to open the connection
/// returns connection CID or an xmlrpcresponse
function db_connect($conndescriptor, $permanent=false)
{
  global $adodbserver_acceptanydb, $adodbserver_connectionarray, $adodbserver_errors;

  // we accept BOTH a connection description (array) OR connection ID (string)!!!
  if (is_array($conndescriptor))
  {
    $cid = getconnectionid($conndescriptor);
    //$conndesc = $conndescriptor;
  }
  else
  {
    $cid = $conndescriptor;
  }

  // check if specified connection is in list of 'openable' connections
  if (array_key_exists($cid, $adodbserver_connectionarray))
  {
    // specified connection is in list
    $conn = $adodbserver_connectionarray[$cid]['adoconnobj'];
    $conndesc = $adodbserver_connectionarray[$cid]['connspec'];
  }
  else
  {

    // specified connection is not in list: are we allowed to open any connection?
    if ($adodbserver_acceptanydb)
    {
      if ($cid != $conndescriptor)
        $conndesc = $conndescriptor;
      else
        // try to decode given SID into a db connection description
        $conndesc = decodeconnectionid($cid);

      if ($conndesc)
      {
        $adodbserver_connectionarray[$cid] = array(
          'adoconnobj' => null,
          'connspeckey' => null,
          'connspec' => $conndesc);
        $conn = null;

        // This should be moved to a global setting, or removed altogether
        if (true)
        {
          global $adodbserver_configfile;
          // save the connection in the config file
          // for use serving subsequent xmlrpc calls to execute
          if (isset($HTTP_SERVER_VARS))
            $remote = $HTTP_SERVER_VARS['REMOTE_ADDR'];
          else
            $remote = $_SERVER['REMOTE_ADDR'];
          $content = "<?php
// db connection definition added automatically.
// call initiated by {$remote} on ".date("l ds of f y h:i:s a")."
\$adodbserver_acceptdb[] = array(
  'driver' => '{$conndesc['driver']}',
  'host' => '{$conndesc['host']}',
  'uid' => '{$conndesc['uid']}',
  'pwd' => '{$conndesc['pwd']}',
  'database' => '{$conndesc['database']}'
);
?>";
          $fp = @fopen($adodbserver_configfile, 'a');
          if ($fp)
          {
            fwrite($fp, $content); //< strlen($content))
            fclose($fp);
          }
        }
      }

      else
      {
        return new xmlrpcresp(
          new xmlrpcval(
          array(
          new xmlrpcval($adodbserver_errors['INVALID_CID'][0], "int"),
          new xmlrpcval($adodbserver_errors['INVALID_CID'][1])
          ), "array")
        );
      }
    } else {
      if ($cid == $conndescriptor)
        // trying to connect to a DB giving ONLY a SID,
        // and the given sid is not listed in our list of available dbs: bomb out
        return new xmlrpcresp(
          new xmlrpcval(
          array(
          new xmlrpcval($adodbserver_errors['INVALID_CID'][0], "int"),
          new xmlrpcval($adodbserver_errors['INVALID_CID'][1])
          ), "array")
        );
      else
        // specified connection is not allowed!
        return new xmlrpcresp(
          new xmlrpcval(
         array(
          new xmlrpcval($adodbserver_errors['DB_BLOCKED'][0], "int"),
          new xmlrpcval($adodbserver_errors['DB_BLOCKED'][1])
          ), "array")
        );
    }
  }

  // Create the desired connection (if it has not been created already)
  if (!$conn) {
    $conn =& ADONewConnection($conndesc['driver']);
//$conn->debug = true;
    if ($conn) {
      // save the connection (strange hack to get assign-by-ref working)
      //$adodbserver_connectionarray[$cid] = array('adoconnobj' => null, 'open' => false);
      $adodbserver_connectionarray[$cid]['adoconnobj'] =& $conn;
      $adodbserver_connectionarray[$cid]['open'] = false;
    } else {
      return stderrorresponse('NO_DRIVER', $conndesc['driver']);
    }
  }
  else
    // ADOConnection object exists and is open
    if ($adodbserver_connectionarray[$cid]['open'])
      return $cid;

//xmlrpc_debugmsg("Connecting to: {$conndesc['host']}, {$conndesc['uid']}, {$conndesc['pwd']}, {$conndesc['database']}");

  // try to open up the connection (if it is not yet open)
  if ($permanent)
    $ok = $conn->PConnect($conndesc['host'], $conndesc['uid'], $conndesc['pwd'],
      $conndesc['database']);
  else
    $ok = $conn->Connect($conndesc['host'], $conndesc['uid'], $conndesc['pwd'],
      $conndesc['database']);

  if ($ok) {
    // ok: mark connection as open and return new connection sid
    //return new xmlrpcresp(
    //  new xmlrpcval($cid),
    //  new xmlrpcval($adodbserver_errors['PCONN_OK'][0], "int"),
    //  new xmlrpcval($adodbserver_errors['PCONN_OK'][1])
    //);
    $adodbserver_connectionarray[$cid]['open'] = true;

    return $cid;
  } else {
    // connection open failed: return adodb connection error code
    return new xmlrpcresp(
      new xmlrpcval(
      array(
      new xmlrpcval($conn->ErrorNo() != 0 ? $conn->ErrorNo() : -1, "int"),
      new xmlrpcval($conn->ErrorMsg())
        ), "array")
    );
  }
}

function db_closeconnection($cid)
{
  global $adodbserver_connectionarray;

  if (array_key_exists($cid, $adodbserver_connectionarray)) {
    $open = $adodbserver_connectionarray[$cid]['open'];
    if ($open) {
      $conn =& $adodbserver_connectionarray[$cid]['adoconnobj'];
      $conn->Close();
      $adodbserver_connectionarray[$cid]['open'] = false;
      return stderrorresponse('CLOSE_OK');
    }
    else
      return stderrorresponse('NOT_CONNECTED');
    // specified connection is in list
  }
  else
    return stderrorresponse('INVALID_CID', $cid);
}

/// runs adoconnection->execute
function db_exec(&$conn, $sql, $params=null)
{
  global $adodbserver_errors;

  if (!is_object($conn))
    error_log('Connerror: '.var_export($conn, true));

  if (!$params)
  {
    $rs =& $conn->Execute($sql);

    if (!$rs)
      return new xmlrpcresp(
        new xmlrpcval(
        array(
        new xmlrpcval($conn->ErrorNo() != 0 ? $conn->ErrorNo() : -1, "int"),
        new xmlrpcval($conn->ErrorMsg())
        ), "array")
      );
    else
    {
      // check if execute returned a sensible recordset object
      $val = null;
      if (is_object($rs))
        if (is_subclass_of($rs, 'adorecordset'))
         $val =& rs2xmlrpcval($rs);
        else
          if (get_class($rs) == 'adorecordset_empty')
            $val =& new xmlrpcval(true, 'boolean');
      if (!$val)
        return new xmlrpcresp(
          new xmlrpcval(
          array(
          new xmlrpcval($adodbserver_errors['UNKNOWN_OBJ'][1], "int"),
          new xmlrpcval($adodbserver_errors['UNKNOWN_OBJ'][1]),
          php_xmlrpc_encode($rs)
          ), "array")
        );
      else
        return new xmlrpcresp(
          new xmlrpcval(
          array(
          new xmlrpcval($adodbserver_errors['SQL_OK'][0], "int"),
          new xmlrpcval($adodbserver_errors['SQL_OK'][1]),
          $val
          ), "array")
        );
    }
  }
  else
  {
    /// @todo Implement db_exec with bound params...
    return stderrorresponse('TOBEDONE');
  }
}

/// runs adoconnection->selectlimit
function db_selectlimit(&$conn, $sql, $numrows=-1, $offset=-1, $params=null)
{
  global $adodbserver_errors;

  if (!is_object($conn))
    error_log('Connerror: '.var_export($conn, true));

  if (!$params)
  {
    $rs =& $conn->SelectLimit($sql, $numrows, $offset);
    if (!$rs)
      return new xmlrpcresp(
        new xmlrpcval(
        array(
        new xmlrpcval($conn->ErrorNo() != 0 ? $conn->ErrorNo() : -1, "int"),
        new xmlrpcval($conn->ErrorMsg())
        ), "array")
      );
    else
      return new xmlrpcresp(
        new xmlrpcval(
        array(
        new xmlrpcval($adodbserver_errors['SQL_OK'][0], "int"),
        new xmlrpcval($adodbserver_errors['SQL_OK'][1]),
        rs2xmlrpcval($rs)
        ), "array")
      );
  }
  else
  {
    /// @todo Implement db_exec with bound params...
    return stderrorresponse('TOBEDONE');
  }
}

/// runs any method on an adoconnection
function db_callconnectionfunction(&$conn, $function, $params=null)
{
  global $adodbserver_errors;

  if (!is_object($conn))
    error_log('Connerror: '.var_export($conn, true));

  if (!method_exists($conn, $function))
    return stderrorresponse('NO_DEF_METH', $function);
  else
  {

//var_dump($function);
//var_dump($params);
//var_dump($conn);

    /// @todo implement error catching functionality so that we can report back to the caller if anything went wrong
    $result = call_user_func_array(array($conn, $function), $params);

    if (is_object($result) && is_a($result, "ADORecordSet"))
    {
      return new xmlrpcresp(
        new xmlrpcval(
        array(
        new xmlrpcval($adodbserver_errors['METH_OK'][0], "int"),
        new xmlrpcval($adodbserver_errors['METH_OK'][1]),
        rs2xmlrpcval($result)
        ), "array")
      );
    }
    else
    {
      return new xmlrpcresp(
        new xmlrpcval(
        array(
        new xmlrpcval($adodbserver_errors['METH_OK'][0], "int"),
        new xmlrpcval($adodbserver_errors['METH_OK'][1]),
        php_xmlrpc_encode($result)
        ), "array")
      );
    }
  }

}

/// List connection specific characteristics. Might add more in the future...
function db_getconnectioncapabilities(&$conn)
{
  return new xmlrpcval(
    array(
      '_bindInputArray' => new xmlrpcval($conn->_bindInputArray),
      'fmtDate' => new xmlrpcval($conn->fmtDate),
      'fmtTimeStamp' => new xmlrpcval($conn->fmtTimeStamp),
      'true' => new xmlrpcval($conn->true),
      'false' => new xmlrpcval($conn->false),
      'concat_operator' => new xmlrpcval($conn->concat_operator),
      'replaceQuote' => new xmlrpcval($conn->replaceQuote),
      'hasLimit' => new xmlrpcval($conn->hasLimit)
    ), "struct");
}

/// Given an error token, create an xmlrpc response detailing the error
function stderrorresponse($errcode, $extraerrinfo=null)
{
  global $adodbserver_errors;

  if (!array_key_exists($errcode, $adodbserver_errors))
    $errcode = 'UNKNOWN';

  return new xmlrpcresp(
    new xmlrpcval( array(
      new xmlrpcval($adodbserver_errors[$errcode][0], "int"),
      new xmlrpcval($adodbserver_errors[$errcode][1].$extraerrinfo)
    ), "array")
  );
}

// ****************************************************************************
// Below here implement all declared functions

function adodbserver_pconnect($m)
{
  return adodbserver_connect($m, true);
}

function adodbserver_connect($m, $permanent=false)
{
  //global $adodbserver_connectionarray, $adodbserver_connectall, $adodbserver_errors;
  global $adodbserver_errors, $adodbserver_connectionarray;

  if ($m->getNumParams() == 5)
  {
    // retrieve input parameters
    $conndesc = array();
    $driver = $m->getParam(0);
    $conndesc['driver'] = $driver->scalarval();
    $host = $m->getParam(1);
    $conndesc['host'] = $host->scalarval();
    $uid = $m->getParam(2);
    $conndesc['uid'] = $uid->scalarval();
    $pwd = $m->getParam(3);
    $conndesc['pwd'] = $pwd->scalarval();
    $database = $m->getParam(4);
    $conndesc['database'] = $database->scalarval();
  }
  else
  {
    if ($m->getNumParams() == 1)
    {
      // one params received: DB connection ID
      $connid = $m->getParam(0);
      $connid = $connid->scalarval();
    }
    else
      // zero params received: try to connect to default DB
      $connid = null;

    $resp = validatecid($connid);

    if (is_object($resp))
      return $resp;
    else
      $conndesc = $resp;
  }

  // all processing offloaded to this function
  $resp = db_connect($conndesc, $permanent);
  if (is_object($resp))
    return $resp;
  else
  {
    // return server connectin characteristics too
    $caps =& db_getconnectioncapabilities($adodbserver_connectionarray[$resp]['adoconnobj']);

    return new xmlrpcresp(
      new xmlrpcval( array(
      new xmlrpcval($adodbserver_errors['PCONN_OK'][0], "int"),
      new xmlrpcval($adodbserver_errors['PCONN_OK'][1]),
      new xmlrpcval($resp),
      $caps
      ), "array")
    );
  }
}

function adodbserver_execute($m)
{
  global $adodbserver_connectionarray, $adodbserver_errors;

  // retrieve input parameters,
  // taking into account multiple signatures
  $n = $m->getNumParams();
  if ($n == 1) {
    // 1 param: sql only
    $connid = null;
    $params = null;
    $sql = $m->getParam(0);
  } elseif ($n == 2) {
    // 2 params
    $p1 = $m->getParam(1);
    if ($p1->kindOf() == "array") {
      $connid = null;
      $sql = $m->getParam(0);
      $params = php_xmlrpc_decode($p1);
    } else {
      $connid = $m->getParam(0);
      $connid = $connid->scalarval();
      $sql =& $p1;
      $params = null;
    }
  } else {
    // 3 params
    $connid = $m->getParam(0);
    $connid = $connid->scalarval();
    $sql = $m->getParam(1);
    $sql = $sql->scalarval();
    $params = $m->getParam(2);
    $params = xmlrpcdecode($params);
  }
  $sql = $sql->scalarval();

  // check that the given connection id is valid (allowed), or die
  $resp = validatecid($connid);

  if (is_object($resp))
    return $resp;
  else {
    //$conn_name = $resp;
    //$conn_id = $connid == null ? getconnectionid($adodbserver_acceptdb[$conn_name]) : $connid;

    // open connection to DB
    // NB: there is surely some duplicate functionality in here with respect to validatecid() above,
    // but using this function saves us a bit of duplicate code
    $resp = db_connect($resp, false);

    if (is_object($resp))
    {
      return $resp;
    }
    else
    {
      return db_exec($adodbserver_connectionarray[$resp]['adoconnobj'], $sql, $params);
    }
  }

}

function adodbserver_selectlimit($m)
{
  global $adodbserver_connectionarray, $adodbserver_errors;

  // retrieve input parameters,
  // taking into account multiple signatures
  $n = $m->getNumParams();
  $connid = $m->getParam(0);
  $sql = $m->getParam(1);
  $typ = $sql->scalartyp();
  if ($typ == 'string') {
    // retrieve input parameters
    //$connid = $m->getParam(0);
    $connid = $connid->scalarval();
    $sql = $sql->scalarval();
    $offset = 1;
  } else {
    $sql = $connid->scalarval();
    $connid = null;
    $offset = 0;
  }
  $nrows = $m->getParam(1+$offset);
  $nrows = $nrows->scalarval();
  if ($n >= 2+$offset) {
    $offset = $m->getParam(2+$offset);
    $offset = $offset->scalarval();
  }
  else
    $offset = -1;

  // check that the given connection id is valid, or die
  $resp = validatecid($connid);

  if (is_object($resp))
    return $resp;
  else
  {
    //$conn_name = $resp;
    //$conn_id = $connid == null ? getconnectionid($adodbserver_acceptdb[$conn_name]) : $connid;

    // open connection to DB
    // NB: there is surely some duplicate functionality in here with respect to validatecid() above,
    // but using this function saves us a bit of duplicate code
    $resp = db_connect($resp, false);

    if (is_object($resp))
    {
      return $resp;
    }
    else
    {
      return db_selectlimit($adodbserver_connectionarray[$resp]['adoconnobj'], $sql, $nrows, $offset, null);
    }
  }

}

function adodbserver_callconnectionfunction($m)
{
  global $adodbserver_connectionarray, $adodbserver_errors;

  // retrieve input parameters,
  // taking into account multiple signatures
  $n = $m->getNumParams();
  if ($n == 3) {
    // retrieve input parameters
    $connid = $m->getParam(0);
    $connid = $connid->scalarval();
    $offset = 1;
  } else {
    if ($n == 2)
    {
      $val2 = $m->getParam(1);
      $typ = $val2->scalartyp();
      if ($typ == 'string')
      {
        $connid = $m->getParam(0);
        $connid = $connid->scalarval();
        $offset = 1;
      }
      else
      {
        $connid = null;
        $offset = 0;
      }
    }
    else
    {
        $connid = null;
        $offset = 0;
    }
  }

  $meth = $m->getParam($offset);
  $params = null;
  if ($n > $offset+1)
  {
    $params = $m->getParam($offset+1);
    if ($params->arraysize() > 0)
      $params = php_xmlrpc_decode($params);
  }

//var_dump($meth);
//var_dump($params);

  // check that the given connection id is valid, or die
  $resp = validatecid($connid);

//var_dump($resp);

  if (is_object($resp))
    return $resp;
  else
  {
    //$conn_name = $resp;
    //$conn_id = $connid == null ? getconnectionid($adodbserver_acceptdb[$conn_name]) : $connid;

    // open connection to DB
    // NB: there is surely some duplicate functionality in here with respect to validatecid() above,
    // but using this function saves us a bit of duplicate code
    $resp = db_connect($resp, false);

//var_dump($adodbserver_connectionarray[$conn_id]);

    if (is_object($resp))
    {
      return $resp;
    }
    else
    {
      // execute specified method on DB connection
      return db_callconnectionfunction($adodbserver_connectionarray[$resp]['adoconnobj'], $meth->scalarval());
    }
  }
}

function adodbserver_close($m)
{
  //global $adodbserver_acceptdb;

  // retrieve input parameters,
  // taking into account multiple signatures
  $n = $m->getNumParams();
  if ($n == 1) {
    // retrieve input parameters
    $connid = $m->getParam(0);
    $connid = $connid->scalarval();
  } else {
    $connid = null;
  }

  // check that the given connection id is valid, or die
  $resp = validatecid($connid);

  if (is_object($resp))
    return $resp;
  else
  {
    // close connection to DB
    return db_closeconnection($resp);
  }
}

function adodbserver_capabilities($m)
{
  global $adodbserver_connectionarray;

  // retrieve input parameters,
  // taking into account multiple signatures
  $n = $m->getNumParams();
  if ($n == 1) {
    // retrieve input parameters
    $connid = $m->getParam(0);
    $connid = $connid->scalarval();
  } else {
    $connid = null;
  }

  // check that the given connection id is valid, or die
  $resp = validatecid($connid);

  if (is_object($resp))
    return $resp;
  else
  {
    // open connection to DB
    // NB: there is surely some duplicate functionality in here with respect to validatecid() above,
    // but using this function saves us a bit of duplicate code
    $resp = db_connect($resp, false);

    if (is_object($resp))
    {
      return $resp;
    }
    else
    {
      $caps =& db_getconnectioncapabilities($adodbserver_connectionarray[$resp]['adoconnobj']);
      return new xmlrpcresp(
        new xmlrpcval(
          array(
          new xmlrpcval($adodbserver_errors['SQL_OK'][0], "int"),
          new xmlrpcval($adodbserver_errors['SQL_OK'][1]),
          $caps
          ), "array")
        );
    }
  }
}

function adodbserver_selectcsv($m)
{
  global $adodbserver_connectionarray, $adodbserver_errors;

  // retrieve input parameters
    $connid = $m->getParam(0);
    $connid = $connid->scalarval();
    $sql = $m->getParam(1);
    $sql = $sql->scalarval();
    $fdelim = $m->getParam(2);
    $fdelim = $fdelim->scalarval();
    $rdelim = $m->getParam(3);
    $rdelim = $rdelim->scalarval();

  // check that the given connection id is valid (allowed), or die
  $resp = validatecid($connid);

  if (is_object($resp))
  {
    //return $resp;
    // decode xmlrpcval into an error response
    $code = $resp->value();
    $desc = $code->arraymem(1);
    $code = $code->arraymem(0);
    return new xmlrpcresp(0, $code->scalarval(), $desc->scalarval());
  }
  else
  {
    //$conn_name = $resp;
    //$conn_id = $connid == null ? getconnectionid($adodbserver_acceptdb[$conn_name]) : $connid;

    // open connection to DB
    // NB: there is surely some duplicate functionality in here with respect to validatecid() above,
    // but using this function saves us a bit of duplicate code
    $resp = db_connect($resp, false);

    if (is_object($resp))
    {
      //return $resp;
      // decode xmlrpcval into an error response
      $code = $resp->value();
      $desc = $code->arraymem(1);
      $code = $code->arraymem(0);
      return new xmlrpcresp(0, $code->scalarval(), $desc->scalarval());
    }
    else
    {
      //return db_exec($adodbserver_connectionarray[$resp]['adoconnobj'], $sql);
      $conn =& $adodbserver_connectionarray[$resp]['adoconnobj'];
      $rs =& $conn->Execute($sql);

      if (!$rs)
        return new xmlrpcresp(0, $conn->ErrorNo() != 0 ? $conn->ErrorNo() : -1, $conn->ErrorMsg());
      else
      {
        // check if execute returned a sensible recordset object
        //$val = null;
        //if (is_object($rs))
        //if (is_subclass_of($rs, 'adorecordset'))
        //{
          $str = '';
          while (!$rs->EOF)
          {
            $str .= implode($fdelim, $rs->fields);
            $str .= $rdelim;
            $rs->MoveNext();
          }
          return new xmlrpcresp(new xmlrpcval($str));
        //}
        //else
        //  if (get_class($rs) == 'adorecordset_empty')
        //    return  new xmlrpcresp(new xmlrpcval('')); // empty line
        //  else
        //  {
        //    return new xmlrpcresp(0, $conn->ErrorNo(), $conn->ErrorMsg());
        //  }
      }
    }
  }

}
?>