<?php
/**
 * Self-documenting extension to the PHP-XMLRPC server
 *
 * @version $Id: docxmlrpcs.inc,v 1.9 2008/03/07 16:47:06 ggiunta Exp $
 * @author Gaetano Giunta
 * @copyright (c) 2005-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 *
 * @todo use some AJAX magic to implement xmlrpc calls to test/debug methods
 *       without feeding to user the raw xml
 * @todo add some i18n support
 * @todo add a sane way to have a set of hhtp headers to be sent along with every
 *       type of generated documentation (eg. content-type)
 **/

	// requires: xmlrpc.inc, xmlrpcs.inc (version 2.0RC3 or later)

	/**
	* Extends the base xmlrpc server with the capability to generate documentation
	* about the exposed xmlrpc methods.
	* It will take advantage of a new memeber in the dispatch map: signature_docs
	* it is expected to be an array with the same number of memebers as signature,
	* but containing a short description for every parameter.
	*/
	class documenting_xmlrpc_server extends xmlrpc_server
	{
		/// default format for generated documentation: either wsdl or html
		var $default_doctype = 'html';
		var $default_doclang = 'en';
		var $supported_langs = array('en');
		var $supported_doctypes = array('html', 'wsdl');
		/// relative path to the visual xmlrpc editing dialog
		var $editorpath = '';

		/**
		* Override xmlrpc_server service method:
		*   in case of GET requests show docs about implemented methods;
		*   in case of POST received by a form, we use the methodCall input value
		*   as if it had been sent with a tex/xml mimetype
		* @param string $data request data to be parsed, null by default
		* @param bool $return_payload when true the payload will be returned but not echoed to screen
		* @param string $doctype type of documentation to generate: html, wsdl, etc... If empty, use class default
		*/
		function service($data=null, $return_payload=false, $doctype='')
		{
			if($_SERVER['REQUEST_METHOD'] != 'POST')
			{
				if ($doctype == '' || !in_array($doctype, $this->supported_doctypes))
				{
					$doctype = $this->default_doctype;
				}
				// language decoding
				if (isset($_GET['lang']) && in_array(strtolower($_GET['lang']), $this->supported_langs))
				{
					$lang = strtolower($_GET['lang']);
				}
				else
				{
					$lang = $this->default_doclang;
				}

				$docs = generateDocs($this, $doctype, $lang, $this->editorpath);
				if (!$return_payload)
				{
					print $docs;
				}
				return $docs;
			}
			else
			{
				// we break the xmlrpc spec here, and answer to POST requests
				// that have been sent via a standard html form, such as the
				// one that is part of self-generated docs
				if(isset($_SERVER['CONTENT_TYPE'])
					&& $_SERVER['CONTENT_TYPE'] == 'application/x-www-form-urlencoded'
					&& isset($_POST['methodCall']))
				{
					if (get_magic_quotes_gpc())
					{
						$_POST['methodCall'] = stripslashes($_POST['methodCall']);
					}
					return parent::service($_POST['methodCall'], $return_payload);
				}
				else
				{
					return parent::service($data, $return_payload);
				}
			}
		}

	}

	/**
	* Generate the documentation about methods exposed by a given server.
	* Note that it will NOT html-escape the user provided documentantation (ie. risky).
	* @param xmlrpcserver $server
	* @param string $doctype type of documentation to generate: html (default), wsdl, etc...
	* @param string $lang language for docs
	* @return string
	*
	* @todo add support for i18n of generated user-readable docs (eg html)
	*/
	function generateDocs($server, $doctype='html', $lang='en', $editorpath='')
	{
		$payload = '';
		switch ($doctype)
		{
			case 'wsdl':
				break;
			case 'html':
				//$i18n = $GLOBALS['xmlrpcdoci18n'][$lang];
				$template = $GLOBALS['xmlrpcdocparts']['html'];
				// in case we have to send custom http headers, do it
				// removed from here, since we only return the payload now...
				//foreach ($template['httpheaders'] as $header)
				//	header($header);

				// method name decoding: is user seeking info about a single method?
				if (isset($_GET['methodName']))
				{
					$opts = array('lang' => $lang, 'title' => 'Method '.$_GET['methodName']);
					if ($editorpath != '')
					{
						$mstart = xmlrpc_smarty($template['xmlrpcmethodstart'], array('method' => $_GET['methodName']));
						$mend = xmlrpc_smarty($template['xmlrpcmethodend'], array());
						$opts['extras'] = xmlrpc_smarty($template['editorheaders'], array('baseurl' => $editorpath, 'methodcallstart' => $mstart, 'methodcallend' => $mend));
					}
					else
						$opts['extras'] = '';
					$payload .= xmlrpc_smarty($template['docheader'], $opts);
					if ($server->allow_system_funcs)
					{
						$methods = array_merge($server->dmap, $GLOBALS['_xmlrpcs_dmap']);
					}
					else
					{
						$methods = $server->dmap;
					}
					if (!array_key_exists($_GET['methodName'], $methods))
					{
						$payload .= xmlrpc_smarty($template['methodheader'], array('method' => $_GET['methodName'], 'desc' => ''));
						$payload .= xmlrpc_smarty($template['methodnotfound'], array('method' => $_GET['methodName']));
					}
					else
					{
						$payload .= xmlrpc_smarty($template['methodheader'], array('method' => $_GET['methodName'], 'desc' => @$methods[$_GET['methodName']]['docstring']));
						//$payload .= xmlrpc_smarty($template['methodfound']);
						for ($i = 0; $i < count($methods[$_GET['methodName']]['signature']); $i++)
						{
							$val = $methods[$_GET['methodName']]['signature'][$i];
							// NEW: signature_docs array, MIGHT be present - or not...
							$doc = @$methods[$_GET['methodName']]['signature_docs'][$i];
							if (!is_array($doc) || !count($doc))
							{
								$doc = array_fill(0, count($val), '');
							}
							$payload .= xmlrpc_smarty($template['sigheader'], array('signum' => $i+1));
							$out = array_shift($val);
							$outdoc = array_shift($doc);
							for ($j = 0; $j < count($val); $j++)
							{
								$payload .= xmlrpc_smarty($template['sigparam'], array('paramtype' => $val[$j], 'paramdesc' => @$doc[$j]));
							}
							$payload .= xmlrpc_smarty($template['sigfooter'], array('outtype' => $out, 'outdesc' => $outdoc, 'method' => $_GET['methodName']));
						}
						if ($editorpath)
						{
							$payload .= xmlrpc_smarty($template['methodfooter'], array('method' => $_GET['methodName'], 'extras' => xmlrpc_smarty($template['editorlink'], array())));
						}
						else
						{
							$payload .= xmlrpc_smarty($template['methodfooter'], array('method' => $_GET['methodName'], 'extras' => ''));
						}
					}
				}
				else
				{
					// complete api info
					$payload .= xmlrpc_smarty($template['docheader'], array('lang' => $lang, 'title' => 'API Index', 'extras' => ''));
					$payload .= xmlrpc_smarty($template['apiheader']);
					foreach($server->dmap as $key => $val)
					{
						$payload .= xmlrpc_smarty($template['apimethod'], array('method' => $key, 'desc' => @$val['docstring']));
					}
					if($server->allow_system_funcs)
					{
						foreach($GLOBALS['_xmlrpcs_dmap'] as $key => $val)
						{
							$payload .= xmlrpc_smarty($template['apimethod'], array('method' => $key, 'desc' => @$val['docstring']));
						}
					}
					$payload .= xmlrpc_smarty($template['apifooter']);
				}

				$payload .= xmlrpc_smarty($template['docfooter']);

		}
		return $payload;
	}

	/**
	* Dumb (dumb dumb) smarty-like template system
	* @param string $template the template text, using {$var} syntax for substitution
	* @param array $params array of variables to be substituted in template, based on array key
	*
	* @todo introduce support for multilanguage directly here
	* @todo introduce support for nested arrays, so we can coalesce templates
	*/
	function xmlrpc_smarty($template, $params=array())
	{
		foreach ($params as $key => $val)
		{
			$template = str_replace("{\$$key}", $val, $template);
		}
		return $template;
	}

	/**
	* Templates used for building docs
	* The charset is assumed to be ISO-8859-1 for every generated html. Take care
	*/
	$GLOBALS['xmlrpcdocparts'] = array(
		'html' => array(

//'httpheaders' => array(),

'docheader' => '<?xml version="1.0" encoding="iso-8859-1"?>
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$lang}" lang="{$lang}">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
<meta http-equiv="Content-Script-Type" content="text-javascript" />
<meta name="generator" content="'.$GLOBALS['xmlrpcName'].'" />
<link rel="stylesheet" type="text/css" href="docxmlrpcs.css" />
{$extras}
<title>{$title}</title>
</head>
<body>',

'docfooter' => '
<div class="footer">Generated using PHP-XMLRPC '.$GLOBALS['xmlrpcVersion'].'</div>
</body></html>',

'apiheader' => '
<h1>API index</h1>
<p>This server defines the following API specification:</p>
<table class="apilist">
<tr><th>Method</th><th>Description</th></tr>',

'apimethod' => '
<tr><td><a href="?methodName={$method}">{$method}</a></td><td>{$desc}</td></tr>',

'apifooter' => '
</table>',

'methodheader' => '
<h1>Method <em>{$method}</em></h1>
<div>{$desc}</div>',

'methodnotfound' => '
<h3>The method {$method} is not part of the API of this server</h3>
',

'sigheader' => '
<h2>Signature {$signum}</h2>
<blockquote>
<h3>Input parameters</h3>
<table class="inputparameters">
<tr><th>Type</th><th>Description</th></tr>',

'sigparam' => '
<tr><td>{$paramtype}</td><td>{$paramdesc}</td></tr>',

'sigfooter' => '
</table>
<h3>Output parameter</h3>
<table class="inputparameters">
<tr><th>Type</th><th>Description</th></tr>
<tr><td>{$outtype}</td><td>{$outdesc}</td></tr>
</table>
</blockquote>',

'methodfooter' => '
<h2>Test method call</h2>
<p>Complete by hand the form below inserting the needed parameters to call this method.<br/>
For a string param use e.g. <pre>&lt;param&gt;&lt;value&gt;&lt;string&gt;Hello&lt;/string&gt;&lt;/value&gt;&lt;/param&gt;</pre></p>
<form action="" method="post"><p>
<textarea id="methodCall" name="methodCall" rows="5" cols="80">
&lt;methodCall&gt;&lt;methodName&gt;{$method}&lt;/methodName&gt;
&lt;params&gt;
&lt;/params&gt;
&lt;/methodCall&gt;
</textarea><br/>
{$extras}
<input type="submit" value="Test"/>
</p></form>',

'editorheaders' => '<script type="text/javascript" src="{$baseurl}xmlrpc_lib.js"></script>
<!--<script type="text/javascript" src="{$baseurl}jsonrpc_lib.js"></script>-->
<script type="text/javascript">
<!--
function runeditor()
{
  //var url = "{$baseurl}visualeditor.php?params={$param_payload}";
  var url = "{$baseurl}visualeditor.php";
  //if (document.frmaction.wstype.value == "1")
  //  url += "&type=jsonrpc";
  var wnd = window.open(url, "_blank", "width=750, height=400, location=0, resizable=1, menubar=0, scrollbars=1");
}
// if javascript version of the lib is found, allow it to send us params
function buildparams(base64data)
{
  if (typeof base64_decode == "function")
  {
    if (base64data == "0") // workaround for bug in base64_encode...
      document.getElementById("methodCall").value = "{$methodcallstart}{$methodcallend}";
    else
      document.getElementById("methodCall").value = "{$methodcallstart}"+base64_decode(base64data)+"{$methodcallend}";
  }
}
//-->
</script>
',

'editorlink' => '<input type="submit" value="Edit" onclick="runeditor(); return false;"/>',

'xmlrpcmethodstart' => '<methodCall><methodName>{$method}</methodName>\n<params>\n',

'xmlrpcmethodend' => '</params>\n</methodCall>',
		),

		'wsdl' => array(
		)
	);

/*
	/// internationalization of docs templates
	$GLOBALS['xmlrpcdoci18n'] = array(
		'en' => array (
			'apiindex' => 'API Index'
		)
	);
*/
?>