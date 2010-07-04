<?php
/**
 * Demo of an ajax jsonrpc server + client in a single php script
 *
 * @version $Id: sonofajax.php 18 2009-06-23 12:57:01Z ggiunta $
 * @author Gaetano Giunta
 * @copyright (c) 2006-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 */

// import required libs
require_once('..\..\xmlrpc\xmlrpc.inc');
require_once('..\..\xmlrpc\xmlrpcs.inc');
require_once('..\jsonrpc\jsonrpc.inc');
require_once('..\jsonrpc\jsonrpcs.inc');
require_once('ajaxmlrpc.inc');

// php functions to be exposed as webservices
function sumintegers ($msg)
{
  $v = $msg->getParam(0);
  $n = $v->arraySize();
  $tot = 0;
  for ($i = 0; $i < $n; $i++)
  {
    $val = $v->arrayMem($i);
    $tot = $tot + $val->scalarval();
  }

  return new xmlrpcresp(new xmlrpcval($tot, 'int'));
}

// webservices signature
// NB: do not use dots in method names
$dmap = array(
'sumintegers' => array(
  'function' => 'sumintegers',
  'signature' => array(array('integer', 'array'))
)
);

// create server object
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$server = new jsonrpc_server($dmap);
	die();
}
?>
<html>
<head>
<?php
// import all webservices from server into javascript namespace
echo js_wrap_dispatch_map($dmap, 'sonofajax.php', 'jsolait', null, 'jsonrpc');
?>
</head>
<body>
Click
<a href="#" onclick="alert(sumintegers([10,11,12])); return false;">here</a>
to execute a webservice call and display results in a popup message...
</body>
</html>