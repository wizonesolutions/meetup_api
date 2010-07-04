<?php
/**
 * Examples for basic xmlrpc adodb proxy
 *
 * @author G. Giunta
 *
 * @version $Id: client_examples.php 3 2009-03-16 20:26:23Z ggiunta $
 *
 * @copyright Copyright (c) 2004-2006 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license.
 *            Whenever there is any discrepancy between the two licenses,
 *            the BSD license will take precedence.
 *
 * @todo add examples with two subsequent calls to connect and execute (db not default)
 *
 **/
?>
<html>
<head><title>adodb xmlrpc examples page</title></head>
<body>
<pre>
<?php
include("xmlrpc.inc");
include("toxmlrpc.inc.php");

  $msgs = array();

// ****************************************************************************
// Example 1
// execute SQL on default remote DB
$msgs[0] = new xmlrpcmsg('adodbserver.execute', array(
  new xmlrpcval('select sysdate from dual'))
  );

// ****************************************************************************
// Example 2
// execute SQL on default remote DB using binding params
$msgs[1] = new xmlrpcmsg('adodbserver.execute', array(
  new xmlrpcval('select ename from emp where sal > ?'),
  new xmlrpcval(array(
    new xmlrpcval(10000, "int")
  ), "array"))
  );

// ****************************************************************************
// Example 3: default SQL on specified remote DB using nrwos and offset
$msgs[2] = new xmlrpcmsg('adodbserver.selectlimit', array(
  new xmlrpcval('select ename from emp where sal > 0'),
  new xmlrpcval(3, "int"),
  new xmlrpcval(-1, "int"))
  );

// ****************************************************************************
// Run examples 1 to 3
foreach ($msgs as $n => $f)
{
  echo "Now running example $n...<br/>";
  $c = new xmlrpc_client("/xmlrpc_cvs/extras/adodb/server/server.php", "localhost", 80);
  $c->setdebug(2);
  $r = $c->send($f);
  $v = $r->value();
  if (!$r->faultCode()) {
    //echo "<hr>I got this value back<br/><pre>" .
    //  htmlentities($r->serialize()). "</pre><hr>\n";
    if ($v->arraysize() > 2) {
      $recordset = $v->arraymem(2);
      $recordset = xmlrpcval2rs($recordset);
      echo "<hr>Converting to ADODB RS...<br/><pre>";
      print_r($recordset);
      echo "</pre><hr>\n";
    }
  } else {
    echo "Fault: ";
    echo "Code: ".$r->faultCode() .
      " Reason '" .htmlspecialchars($r->faultString())."'<br/>";
  }
}


?>
</pre>
</body>
</html>