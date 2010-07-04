<?php
/**
 * Very simple xmlrpc proxy server. Forwards all requests to sf.net server
 *
 * @author gaetano Giunta
 * @version $Id: proxyserver.php 18 2009-06-23 12:57:01Z ggiunta $
 * @copyright (c) 2006-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 */

include('xmlrpc.inc');
include('xmlrpcs.inc');
include('proxyxmlrpcs.inc');

$server = new proxy_xmlrpc_server(new xmlrpc_client('http://phpxmlrpc.sourceforge.net/server.php'), false);
$server->setDebug(2);
$server->service();
?>