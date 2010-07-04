<?php
/**
 * Basic xmlrpc adodb proxy: this page will service incoming xmlrpc requests.
 * 
 * @author G. Giunta
 * 
 * @version   $Revision: 1.4 $ $Date: 2008/03/07 16:47:06 $ $Author: ggiunta $
 * 
 * @copyright Copyright (c) 2004-2009 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license. 
 *            Whenever there is any discrepancy between the two licenses, 
 *            the BSD license will take precedence.
 **/

// ****************************************************************************
// All configuration is normally done in an external file.
// Here is defined the name of the config. file
// This allows to copy this file (server.php) verbatim to multiple servers, leaving the
// install-dependent part in the config. file

/// config. defaults (try to avoid parameter injection as much as possible)
$adodbserver_functionarray = array();
$adodbserver_debuglvl = 0;
$adodbserver_class = 'xmlrpc_server';

/// config. file location
$adodbserver_configfile = getcwd()."/server_config.inc.php";

//include config. file
include($adodbserver_configfile);


// include file with the list and implementation of the exposed functions
// Note: we keep the list separate because this way it integrates more easily 
// with extended xmlrpc frameworks
/// function file location
$adodbserver_functionfile = getcwd()."/server_functions.inc.php";

//include config. file
include($adodbserver_functionfile);



// ****************************************************************************
/// Do all the the needed xmlrpc magic!
$s =& new $adodbserver_class($adodbserver_functionarray, false);
$s->setDebug($adodbserver_debuglvl);
$s->service();

?>