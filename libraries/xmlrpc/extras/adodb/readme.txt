>> XMLRPC Library for ADODB

(c) 2006, 2007 Gaetano Giunta

Released under both BSD and GNU Lesser GPL library license. 
This means you can use it in proprietary products.

 
>> NOTE NOTE NOTE

Code and especially documentation are in EARLY BETA stage.
Use at your own risk.


>> Introduction

The main purpose of this library is to provide a flexible and easy-to-use database-to-webservice conversion mechanism.
In layman terms, we want to provide an easy mean of connecting applications to remote databases using a web service protocol instead of a native database driver.
To meet this goal we have based our work on  the excellent ADODB database access library maintained by John Lim and PHP-xmlrpc web service library originally written by Edd Dumbill.

Features (marketing hype):
- written in 100% PHP, runs on Windows, Linux and many variants of UNIX
- use of ADODB as database backend enables connection to a great number of different databases (even LDAP!)
- uses XML over HTTP as data transportation layer: can be deployed in different setups and will work across proxies, firewalls etc...
- uses a standard web-service protocol with libraries available in many programming languages, allowing coders to write new clients for integration within existing platforms
- includes a generic SQL-to-HTTP proxy that can be used to build two or three-tier application architectures eliminating the need for installation of database connectivity on clients
- can be used to save money on database licensing costs :)
- if properly coded, clients can become 100% database platform agnostic, i.e. you could switch databases with zero impact on clients and limited impact on the middle-tier
- many, many times slower than accessing databases using native driver interfaces
- does not take advantage of many particular programming features of advanced databases


>> Documentation and Examples

This distribution consists of 3 main modules:
1 - an adodb-recordset to xmlrpc-value (and viceversa) conversion module for adodb, forming the core of the library
2 - an xmlrpc web-service server that provides the functionality of a SQL-to-HTTP proxy
3 - an xmlrpc driver for ADODB that can be used to transparently connect applications to remote databases using the xmlrpc web-service server

Module 1 can be used stand-alone in any application; module 2 depends on module 1 and module 3 depends on both 1 and 2.

Before you go any further make sure you have a minimum understanding with the basics of web services. The XML-RPC specification and a lot of useful documentation is available at www.xmlrpc.com.

Then take a look at the schema documenting a typical usage scenario (file schema.svg). We will refer to that image througout the rest of the documentation.

- part A is the database to be accessed by the client app. It can be any database supported by adodb.
- part B is the PHP-powered web service that lets remote clients access the database using xmlrpc function calls. It needs a php-supporting web server to run.
- part C is the client application, and is the most varying of the 3 parts:
  + it can be written in PHP and make use of the adodb xmlrpc driver (module 3 above). The programmer only needs to know the adodb api.
  + it can be written in PHP and make direct use of xmlrpc calls. The programmer needs to know the phpxmlrpc api and decode 'by hand' the recordset received from the db.
  + it can be written in any other language than PHP, making use of an approriate xmlrpc library.  
  + it can be part of a website as well as a stand-alone application

Needless to say, all 3 parts can reside on the same as well as on 3 completely separate physical servers (any combination is allowed).

A few usage cases:

- A is SQLserver, B is IIS. They both reside on the same Windows 200 server. C is a PHP application running in Apache on Linux. Advantage: no need of ODBC drivers on the linux server
- A is Sybase, B is Apache. They both reside on the same Solaris server. C is is a PHP application running as a service on a SCO Unix. Advantage: no need for recent Sybase client libraries when compiling PHP on SCO.
- A is Oracle, B is Netscape Fasttrack. They reside on different servers in the intranet. C is a PHP application running in Apache on a hardened Solaris in the DMZ. Advantage: instead of giving direct access to the internal database through the firewall separating the intranet and DMZ networks, only a (custom) port is opened for HTTP request.

>> Files

adodb-xmlrpc.inc.php	      adodb driver that connects to SQL-to-XMLRPC proxies
client_driver_examples.php    examples of usage of the
client_examples.php           examples of clients for the SQL-to-XMLRPC proxy
readme.txt                    this file
schema.svg                    an image illustrating components of the library
server.php                    the 'main' file of the SQL-to-XMLRPC proxy (i.e. the one to be used in the web-service URL). It usually does not have to be modified for deployment.
server_config.inc.php         the file where the configuration of the SQL-to-XMLRPC proxy is stored. Has to be deployed on the web-services server and tailored for every site.
server_functions.inc.php      core functionality of the SQL-to-XMLRPC proxy. Has to be deployed as-is on the web-services server
toxmlrpc.inc.php              xmlrpc to adodb conversion library. Needed by many of the other files.


>> Installation

Before you go any further, make sure you have downloaded and properly installed both ADODB and PHP-xmlrpc

ADODB is avalibale from http://php.weblogs.com/adodb
PHP-xmlrpc is available from Sourceforge: http://sourceforge.net/projects/phpxmlrpc

Server side (tier B of schema)
- unpack the php xmlrpc lib and put it into a directory where it can be included by php scripts (see directive include in php.ini). NB: only the two files xmlrpc.inc and xmlrpcs.inc a re actually needed. NNB: to avoid potential security problems possibly put them in a directory outside of the web server root!
- unpack the adodb lib and put it into a directory where it can be included by php scripts NNB: to avoid potential security problems possibly put it in a directory outside of the web server root!
- unpack in a temp dir the contents of this distribution, then
  - copy toxmlrpc.inc.php in the main adodb dir
  - copy adodb-xmlrpc.inc.php in ADODB/drivers
  - choose a directory inside the web server root where the web service will be active. Copy in there the files server.php, server_config.inc.php and server_functions.inc.php
- edit the configuration of the web service: edit the file server_config.inc.php

Client side (tier C of schema)
- unpack the php xmlrpc lib and put it into a directory where it can be included by php scripts (see directive include in php.ini). NB: only the two files xmlrpc.inc and xmlrpcs.inc a re actually needed. NNB: to avoid potential security problems possibly put them in a directory outside of the web server root!
- unpack the adodb lib and put it into a directory where it can be included by php scripts NNB: to avoid potential security problems possibly put it in a directory outside of the web server root!
- unpack in a temp dir the contents of this distribution, then
  - copy toxmlrpc.inc.php in the main adodb dir
  - copy adodb-xmlrpc.inc.php in ADODB/drivers


>> Road Map

- add support in the SQL-to-XMLRPC proxy for the missing 90% functionality of adodb, such as sql bind parameters
- create a stand-alone web-service server making use of a continuosly running php script (e.g. nanoweb or pear::http) instead of using an external web server
- benchmark for speed / bandwidth
- see all todo items in the code


>> More Info

XML-RPC is a format devised by Userland Software for achieving remote procedure call via XML. XML-RPC has its own web site, www.xmlxpc.com
PHP-xmlrpc is an implementation of xml-rpc in PHP written originally by Edd Dumbill. It is now available on Sourceforge. There is a mailing list available on ...
ADODB is a database access library written and maintained by John Lim. There is an active forum on the web at http://phplens.com/lensforum?forumID=4

JAVADOC-generated documentation can be found in the doc subdirectory.
 
The code is commented 'quite a lot'. Take a look at the source if you have troubles making it work, or to understand the usage patterns / function calls.
Long lists of todo items can also be found at the beginning of each file, in case you feel the urge to contribute.

This documentation is a work in progress: details will be added in future releases.


>> Feature Requests and Bug Reports

http://sourceforge.net/projects/phpxmlrpc
