<?php
/**
 * Reverse proxy extension to the PHP-XMLRPC server.
 *
 * @version $Id: proxyxmlrpcs.inc,v 1.3 2008/03/07 16:47:32 ggiunta Exp $
 * @author Gaetano Giunta
 * @copyright (c) 2006-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 **/

	// requires: xmlrpc.inc, xmlrpcs.inc (version 2.0RC3 or later)

	class proxy_xmlrpc_server extends xmlrpc_server
	{
		var $client = null;
		var $execute_any_call = true;

		function do_proxy($m)
		{
			xmlrpc_debugmsg('Forwarding method '.$m->method().' to server '.$this->client->server);

			return $this->client->send($m);
		}

		/**
		* Override constructor: we always need a client object to be used for
		* communicating with remote server, and have no dispatch map of ours
		*/
		function proxy_xmlrpc_server($client, $serviceNow=true)
		{
			// duplicate $client into $this->client, but use faster response decoding
//			if (version_compare(phpversion(), '5.0', 'ge'))
//			{
//				$this->client = clone $client;
//			}
//			else
//			{
				$this->client = $client;
//			}
			$this->client->return_type = 'xmlvals';
			$this->client->setDebug(0);

			$this->xmlrpc_server(null, $serviceNow);
		}

		/**
		* Override execute function, to add support for both modes of operation:
		* 1 - proxy ANY call to remote server
		* 2 - proxy only selected methods to remote server
		*/
		function execute($m, $params=null, $paramtypes=null)
		{
			// this class of servers does not work in 'phpvals' mode
			if (!is_object($m))
				return new xmlrpcresp(0, $GLOBALS['xmlrpcerr']['server_error'],
					$GLOBALS['xmlrpcstr']['server_error'].': changing functions_parameters_type parameter of proxy server is not supported');

			if ($this->execute_any_call)
			{
				return $this->do_proxy($m);
			}
			else
			{
				return parent::execute($m);
			}
		}

		/**
		* Add to dispatch map of this server some (or all) of the methods
		* exposed by a remote server.
		* @return false on error, or the number of methods proxyed
		*/
		function acquire_server_methods($methodlist=null)
		{

			if (is_string($methodlist))
			{
				$methodlist = array($methodlist);
			}
			else if ($methodlist == null)
			{
				// retrieve complete list of methods from remote server
				$msg = new xmlrpcmsg('system.listMethods', array());
			 	$methodlist = $this->client->send($msg);
			 	if ($methodlist->faultCode())
				{
			 		return false;
			 	}
			 	else
			 	{
			 		$methodlist = php_xmlrpc_decode($methodlist->value());
			 	}
			}

			// for each remote method to be exposed, query the syntax
			$ok = 0;
			foreach($methodlist as $method)
			{
				// do not creplicate remote system. methods
				if (strpos($method, 'system.') !== 0) {

				$msg = new xmlrpcmsg('system.methodSignature', array(new xmlrpcval($method)));
			 	$sig = $this->client->send($msg);
			 	if (!$sig->faultCode())
				{
					$sig = php_xmlrpc_decode($sig->value());
					if (!is_array($sig)) // remote method sig: undefined
					{
						$sig = null;
					}
			 		// method sig OK, now query method desc
					$msg = new xmlrpcmsg('system.methodHelp', array(new xmlrpcval($method)));
			 		$desc = $this->client->send($msg);
			 		if ($desc->faultCode())
					{
			 			$desc = '';
			 		}
			 		else
					{
			 			$desc = $desc->value();
			 			$desc = $desc->scalarval();
			 		}
			 		$this->add_to_map($method, array($this, 'do_proxy'), $sig, $desc);
			 		$ok++;
			 	}

				}
			}
			return $ok;
		}
	}

?>