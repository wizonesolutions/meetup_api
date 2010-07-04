<?php
/**
 * Basic xmlrpc adodb proxy: server configuration file
 *
 * @author G. Giunta
 *
 * @version $Id: server_config.inc.php 18 2009-06-23 12:57:01Z ggiunta $
 *
 * @copyright Copyright (c) 2004-2009 Gaetano Giunta. All rights reserved.
 *            Released under both BSD license and Lesser GPL library license.
 *            Whenever there is any discrepancy between the two licenses,
 *            the BSD license will take precedence.
 *
 * Some server-wide tailoring parameters.
 * Chances are you'll only need to modify this file ;)
 * It is automatically included by server.php
 *
 * TAKE CARE: this file can be altered automatically by PHP:
 * if the server is set to use database connection definitions
 * specified by the caller rather than only a pre-defined set
 * of connections, the definitions of connections initiated by
 * xmlrpc calls will be appended at the end!
 **/

/**
* In a production site, we should not break xmlrpc calling convenctions
* sending to the client some html junk: disable echoing of errors to http.
* This is also quite important for security reasons!
*/
///ini_set('display_errors', '0');

/**
* The same applies for ADODB error messages produced by ADOConnection::outp
* we route them inside the xmlrpc response as comments...
*/
define('ADODB_OUTP', 'xmlrpc_adodb_proxy_outp');


/**
* Server class, in case you want to override it with a subclass...
*/
global $adodbserver_class;
$adodbserver_class = 'xmlrpc_server';

/**
* Server debug level (0-3)
*/
global $adodbserver_debuglvl;
$adodbserver_debuglvl = 3;

/**
* Used in creating DB connection id descriptors.
* Should be changed for every installation
*/
global $adodbserver_magic;
$adodbserver_magic = 'Change me on install';

/**
 * Define the IP address you want to accept requests from
 * as an extra (very mild) security measure.
 * If blank/null we accept anyone promisciously!
 * If you need to accept more than one IP, make it an array.
 * Note: localhost is always accepted!
 *
 * @todo: accept ranges of IP, using Apache-style syntax (e.g. 10.0)
 * @todo: check out if names can be used or IP addresses only..,
 */
global $adodbserver_acceptip;
$adodbserver_acceptip = '';

/**
* Decide wheter the caller can connect to any database or
* only to the databases specified in the list below
*/
global $adodbserver_acceptanydb;
$adodbserver_acceptanydb = true;

/**
* Define the databases you want to allow the callers to connect to,
* as a security measure (with $adodbserver_acceptanydb = false) or for simplicity of use.
* Please make sure it is an array of valid connection definitions.
* NB: the keys used in this array are NOT meaningful, except for 'default'
*/
global $adodbserver_acceptdb;
$adodbserver_acceptdb = array (

/*
* Connection parameters for default connection.
* If you want no default connection, you can comment it out
* or simply rename it from 'default' to anything else
*/
  'default' => array (
    'driver' => 'oci8',
    'host' => '127.0.0.1', // DSN for odbc, IP for others
    'uid' => 'scott',
    'pwd' => 'tiger',
    'database' => 'test' // SID for oci, DB for msysql, sqlserver, etc...
  )

);
?>
