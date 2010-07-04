<pre>
<?php
/**
 * Testing framework for xmlrpc driver for adodb
 *
 * @author G. Giunta
 *
 * @version $Id: client_driver_examples.php 3 2009-03-16 20:26:23Z ggiunta $
 *
 * @copyright Copyright (c) 2004-2006 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license.
 *            Whenever there is any discrepancy between the two licenses,
 *            the BSD license will take precedence.
 **/

  include('adodb.inc.php');
  //include('xmlrpc.inc');

  // list of client classes (drivers) to use for testing, against different xmlrpc endpoints
  $testservers = array(
    // XML-RPC SERVER on LOCALHOST
    'xmlrpc' => 'localhost/xmlrpc_cvs/extras/adodb/server/server.php',
  );

  // list of functions to call, with parameters
  $testfunctions = array(
    'ServerInfo' => null,
    'Execute' => 'select sysdate from dual'
  );

  $numtests = count($testfunctions) * count($testservers);
  $testspassed = 0;

  foreach ($testservers as $conn_class => $server_addr)
  {
    $conn =& adonewconnection($conn_class);

    if ($conn)
    {
      echo "CREATED DB CONNECTION WITH XMLRPC DRIVER: $conn_class\n";
      $conn->debug = true;

      /*
      $conn->setProxy('/adodb/contrib/server2.php', 'localhost');
      if ($conn->connect())
      {
        $list =& $conn->ServerInfo();
        var_dump($list);
        //$conn->close();
      }
      echo "ERRONO: [".$conn->ErrorNo()."]\n";
      echo "ERROMSG: [".$conn->ErrorMsg()."]\n";
      */

      if ($conn->connect($server_addr))
      {
        echo "CONNECTED TO SERVER: $server_addr\n";

        foreach ($testfunctions as $func => $args)
        {
          echo "TESTING FUNCTION: $func($args)\n";
          $res = $conn->$func($args);
          if ($conn->ErrorNo())
          {
            echo "ERRONO: [".$conn->ErrorNo()."]\n";
            echo "ERROMSG: [".$conn->ErrorMsg()."]\n";
          }
          else
          {
            $testspassed++;
            echo "RESULTS: ";
            var_dump($res);
          }
          flush();
        }

      }
      else
      {
        echo "ERROR: CANNOT CONNECT TO SERVER: $server_addr\n";
        echo "ERRONO: [".$conn->ErrorNo()."]\n";
        echo "ERROMSG: [".$conn->ErrorMsg()."]\n";
      }
    }

    else
      echo "ERROR: CANNOT CREATE DB CONNECTION WITH XMLRPC DRIVER: $conn_class\n";

    echo "\n<hr>\n";
    flush();
  }

  echo "PASSED $testspassed TESTS OUT OF $numtests";
?>
</pre>