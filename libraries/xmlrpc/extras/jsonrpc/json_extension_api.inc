<?php
/**
 * Try to implement the same API of the PHP built-in JSON extension, so that
 * projects relying on it can be ported to php installs where the extension is
 * missing.
 *
 * If the xmlrpc extension is loaded, all new functions will be created as 'alt',
 * ie: json_alt_encode, json_alt_decode
 *
 * @version $Id: json_extension_api.inc,v 1.8 2008/03/07 16:47:32 ggiunta Exp $
 * @author Gaetano Giunta
 * @copyright (c) 2006-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 *
 * @requires phpxmlrpc version 2.1 or later
 */

// requires: xmlrpc.inc
// requires: jsonrpc.inc

// In default operating mode, the internal charset that the php extension assumes is UTF-8
// so let's emulate it to our best, whilst givig user a chance to change this behaviour...
$GLOBALS['xmlrpc_internalencoding'] = 'UTF-8';

// We allow the user to decide wheter decoding '"a"', '1', 'true', 'null' works
// or returns false (the php extension changed this behaviour halfway...
if (version_compare(phpversion(), '5.2.1') >= 0 || (version_compare(phpversion(), '4.4.5') >= 0 && version_compare(phpversion(), '5.0') < 0))
{
  $GLOBALS['json_extension_api_120_behaviour'] = false;
}
else
{
  $GLOBALS['json_extension_api_120_behaviour'] = true;
}

if (!in_array('json',get_loaded_extensions()))
{
  /* constants: since php 5.3.0 */
  define('JSON_ERROR_NONE', 0);
  define('JSON_ERROR_DEPTH', 1);
  define('JSON_ERROR_STATE_MISMATCH', 2);
  define('JSON_ERROR_CTRL_CHAR', 3);
  define('JSON_ERROR_SYNTAX', 4);
  define('JSON_HEX_TAG', 1);
  define('JSON_HEX_AMP', 2);
  define('JSON_HEX_APOS', 4);
  define('JSON_HEX_QUOT', 8);
  define('JSON_FORCE_OBJECT', 16);

  $GLOBALS['_json_last_error'] = 0;

  /**
  * Takes a php value (array or object) and returns its json representation
  * @param mixed $value
  * @return string
  *
  * @bug if value is an ISO-8859-1 string with chars outside of ASCII, the php extension returns NULL, and we do not...
  */
  function json_encode($value, $options=0)
  {
    $jsval = php_jsonrpc_encode($value, array());
    // make sure we emulate the std php behaviour: strings to be encoded are UTF-8!!!
    return $jsval->serialize();
  }

  /**
  * Takes a json-formetted string and decodes it into php values
  * @param string $json
  * @param bool $assoc
  * @return mixed
  *
  * @todo add support for assoc=false
  */
  function json_decode($json, $assoc=false, $depth=512)
  {
    $jsval = php_jsonrpc_decode_json($json);
    if (!$jsval)
    {
      $GLOBALS['_json_last_error'] = 4;
      return NULL;
    }
    else
    {
      // up to php version 5.2.1, json_decode only accepted structs and arrays as top-level elements
      if ($GLOBALS['json_extension_api_120_behaviour'] && ($jsval->mytype != 3 && $jsval->mytype != 2))
      {
        $GLOBALS['_json_last_error'] = 4;
        return NULL;
      }
      $options = $assoc ? array() : array('decode_php_objs');
      $val = php_jsonrpc_decode($jsval, $options);
      $GLOBALS['_json_last_error'] = 0;
      return $val;
    }
  }

  function json_last_error()
  {
    return $GLOBALS['_json_last_error'];
  }
}
else
{
  // in case json extension is already loaded, register all funcions w.
  // different names, eg. json_alt_encode
  $content = file_get_contents(__FILE__);
  $content = str_replace(array("!in_array('json',get_loaded_extensions())", 'function json_', "define('JSON_", '<?php', '?>'), array('true', 'function json_alt_', "define('JSON_ALT_", '', ''), $content);
  eval($content);
}
?>