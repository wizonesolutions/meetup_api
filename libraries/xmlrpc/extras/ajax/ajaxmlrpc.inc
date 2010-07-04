<?php
/**
 * AJAX extension to the PHP-XMLRPC lib (works with jsonrpc, too).
 *
 * Makes use of either the js-xmlrpc lib or the excellent Jsolait javascript
 * library by Jan-Klaas Kollhof
 *
 * Original idea taken from the PHP-O-Lait library by Craig Mason-Jones
 *
 * @version $Id: ajaxmlrpc.inc,v 1.6 2008/03/07 16:47:32 ggiunta Exp $
 * @author Gaetano Giunta
 * @copyright (c) 2006-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 *
 * @todo add a js object wrapper for all web services if user prefers oop instead
 *       of plain function names (see php-o-lait for an example),
 *       or at least a name prefix for all created functions, to prevent js namespace pollution
 * @todo find a fix for xmlrpc methods whose name contain chars invalid in js function names (eg. '.')
 * @todo if we sbuclass jsonrpc server, we can use all this magic with json...
 * @todo find a better way of handling webservice errors than using a js alert()
 * @todo add support for json with jsxmlrpc (only jsolait supports it currently)
 */

	// requires: xmlrpc.inc, xmlrpcs.inc

	class js_rpc_server extends xmlrpc_server
	{
		var $jsLibsPath = 'jsolait'; // default url of jsolait/jsxmlrpc lib: same dir as php script...
		var $selfUrl;
		/// Javascript lib in use: either 'jsolait' or 'jsxmlrpc'
		var $jsLibsType = 'jsolait';

		/**
		* Override base class creator, since we take care of either executing
		* or just preparing the server
		* (this means on creating class we should either execute-and-die or
		* basically do nothing. Use $servicenow=false only for debugging)
		*/
		function js_rpc_server($dispMap=null, $serviceNow=true)
		{
			// Check if this page has been called via XMLRPC or not
			if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['CONTENT_TYPE']) &&
			strpos(strtolower($_SERVER['CONTENT_TYPE']), 'text/xml') === 0)
			{
				// xmlrpc call received: serve web service and die
				parent::xmlrpc_server($dispMap, $serviceNow);
				die();
			}
			else
			{
				// standard page request received: prepare self to emit javascript
				// code that will call self
				$this->selfUrl = $_SERVER['REQUEST_URI'];
				parent::xmlrpc_server($dispMap, false);
			}
		}

		/**
		* Echo js code that wraps calls to server.
		* Note that method names with a . will not work!
		* @param array $methodlist list of methods to be wrapped. Null = all server methods (except system.XXX methods)
		*/
		function importMethods2JS($methodlist=null)
		{
			echo js_wrap_xmlrpc_server($this, $this->selfUrl, $this->jsLibsPath, $methodlist, $this->jsLibsType);
		}
	}

	/**
	* Return html code to include all needed jsolait / jsxmlrpc libs
	* @param string $jsLibsPath
	* @param string $jsLIbsType either 'jsolait' or 'jsxmlrpc'
	* @return string
	*/
	function js_import_libs($jsLibsPath, $jsLibsType='jsolait')
	{
		if ($jsLibsPath == '')
		{
			$jsLibsPath = '.';
		}
		if ($jsLibsType == 'jsolait')
		{
			$out = '<script type="text/javascript" src="'.$jsLibsPath.'/init.js"></script>
';
		}
		else
		{
			$out = '<script type="text/javascript" src="'.$jsLibsPath.'/xmlrpc_lib.js"></script>
';
		}
		return $out;
	}

	/**
	* Return js code providing a function to call a method of an xmlrpc server.
	* Note that method names should NOT contain dot characters, nor url contain double quotes
	* @todo catch run-time xmlrpc exceptions without a js alert
	*/
	function js_wrap_xmlrpc_method($method, $url, $liburl='', $protocol='xmrlpc', $jsLibsType='jsolait')
	{
		//$method = str_replace('.', '_', $method);
		if ($jsLibsType == 'jsolait')
		{
			$out = 'function '.$method.' (args) {
	var '.$protocol.' = null;
	var '.$protocol.'_server = null;';
	/*if ($baseurl != '')
	{
		$out .= '
	jsolait.baseURL = '.$baseurl;
	}*/
			if ($liburl != '')
			{
				$out .= '
	jsolait.libURL = "'.$liburl.'"';
			}
			$out .= '
	try {
		'.$protocol.' = importModule("'.$protocol.'");
		'.$protocol.'_server = new '.$protocol.'.ServiceProxy("'.$url.'", ["'.$method.'"]);
		try {
			return '.$protocol.'_server.'.$method.'(args);
		} catch (e) {
			alert(e);
		}
	} catch (e) {
		alert(e);
	}
}
';
		}
		else
		{
			$out = 'function '.$method.' () {
	var ws_client = new '.$protocol.'_client("'.$url.'");
	var ws_args = [];
	for (var i = 0; i < arguments.length; i++)
	{
		ws_args[ws_args.length] = '.$protocol.'_encode(arguments[i]);
	}
	var ws_msg = new '.$protocol.'msg("'.$method.'", ws_args);
	try {
		var ws_resp = ws_client.send(ws_msg);
		if  (ws_resp.faultCode())
			alert("ERROR: " + ws_resp.faultCode() + " - " + ws_resp.faultString());
		else
			return  '.$protocol.'_decode(ws_resp.value());
	} catch (e) {
		alert(e);
	}
}
';
		}
		return $out;
	}

	/**
	* Return js code providing functions to call all methods of an xmlrpc server
	* Note that method names with a . will not work!
	* @param xmlrpcserver $server
	* @param array $methodlist list of methods to be wrapped into js (or null == expose all methods)
	* @param string $url url at which the xmlrpcserver responds
	* @param string $liburl url of the jsolait lib (see jsolait docs for more info)
	* @param string $jsLibsType either 'jsolait' or 'jsxmlrpc'
	* @return string the js code snippet defining js functions that wrap web services
	*/
	function js_wrap_xmlrpc_server($server, $url, $liburl='', $methodlist=null, $jsLibsType='jsolait')
	{
			if (is_a($server, 'jsonrpc_server'))
			{
				$protocol = 'jsonrpc';
			}
			else
			{
				$protocol = 'xmlrpc';
			}
			return js_wrap_dispatch_map($server->dmap, $url, $liburl, $methodlist, $protocol, $jsLibsType);
	}

	/**
	* Same functionality as 'js_wrap_xmlrpc_server', but needs only the server
	* dispatch map (and an optional server type) , not the server itself
	* @param array $dmap an xmlrpc server dispatch map
	* @param array $methodlist list of methods to be wrapped into js (or null = wrap all)
	* @param string $url url at which the xmlrpcserver responds
	* @param string $liburl url of the jsolait lib (see jsolait docs for more info)
	* @param string $protocol either 'xmlrpc' or 'jsonrpc'
	* @param string $jsLIbsType either 'jsolait' or 'jsxmlrpc'
	* @return string the js code snippet defining js functions that wrap web services
	*/
	function js_wrap_dispatch_map($dmap, $url, $liburl='', $methodlist=null, $protocol='xmlrpc', $jsLibsType='jsolait')
	{
			$out = '';
			$out .= js_import_libs($liburl, $jsLibsType);
			if ($methodlist == null)
			{
				$methodlist = array_keys($dmap);
			}
			// be extra tolerant: a single method can be passed as string...
			else if (is_string($methodlist))
			{
			  $methodlist = array($methodlist);
			}

			$out .= '<script type="text/javascript">
<!--
';
			foreach($methodlist as $method)
			{
				if (array_key_exists($method, $dmap))
				{
					$out .= js_wrap_xmlrpc_method($method, $url, $liburl, $protocol, $jsLibsType);
				}
			}
			$out .= '//-->
</script>
';
			return $out;
	}

?>