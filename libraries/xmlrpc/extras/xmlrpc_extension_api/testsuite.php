<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
<head>
<title>XMLRPC Tetsuite</title>
<style type="text/css">
body { font-family: courier; font-size: small; }
td { border: 1px solid silver; vertical-align: top; }
.diff { font-weight: bold; background-color: silver; }
b { background-color: #eeeeee; }
</style>
</head>
<body>
<?php
/**
 * @todo test xmlrpc_encode_request(NULL, array())
 *
 * @version $Id: testsuite.php 45 2009-08-04 22:36:35Z ggiunta $
 * @copyright 2006
 */

  require_once 'phpunit.php';
  require_once 'PHPUnit/TestDecorator.php';
  require_once 'xmlrpc.inc';
  require_once 'xmlrpcs.inc';
  require_once 'xmlrpc_extension_api.inc';


  class apitests //extends PHPUnit_TestCase
  {
    function testEncoding()
    {
      $v1 = '20060707T12:00:00';
      xmlrpc_alt_set_type($v1, 'datetime');
      $v2 = 'hello world';
      xmlrpc_alt_set_type($v2, 'base64');
      $v3 = fopen(__FILE__, 'r');
      $vals = array(
        true,
        false,
        0,
        1,
        1.0,
        1.1,
        1.123456789,
        '',
        null, // base 64 type???, encoded as empty string
        '1',
        '20060101T12:00:00',
        $v1,
        base64_encode('hello'), // string
        $v2,
        $v3,
        array(),
        array('a'),
        array(array(1)),
        array('2' => true, false), // array - when decoded array keys will be reset
        array('hello' => 'world'), // struct
        array('hello' => true, 'world'), // mixed
        array('hello' => true, 'hello', 'world'), // mixed - encode KO (2 memebers with null name) but decode will be fine!!!
        array('methodname' => 'hello', 'params' => array()), // struct
        array('faultCode' => 666, 'faultString' => 'hello world'),
        array('faultCode' => 666, 'faultString' => 'hello world', 'faultWhat?' => 'dunno'),
        array('faultCode' => 666, 'faultString' => array('hello world')),
        //new apitests() // CRASH!!!,
      );

      echo "<table>\n<thead><tr><th>ORIGINAL VALUE</th><th>XMLRPC TYPE</th><th>ALT TYPE</th><th>XMLRPC ENCODED</th><th>ALT ENCODED</th><th>DECODED VAL</th><th>ALT DECODED</th><th>XMLRPC REQUEST</th><th>ALT REQ</th><th>DECODED REQ</th><th>ALT DEC REQ</th><th>XMLRPC RESP</th><th>ALT RESP</th><th>DECODED RESP</th><th>ALT DEC RESP</th></tr></thead>\n";
      foreach ($vals as $val)
      {
        echo '<tr><td>';
        var_dump($val);

        echo '</td><td>';
        $ok = xmlrpc_get_type($val);
        echo $ok;
        echo '</td><td>';

        $ok1 = xmlrpc_alt_get_type($val);
        if ($ok !== $ok1) echo '<b>'.$ok1."</b>\n";
        echo '</td><td>';

        $ko = xmlrpc_encode($val);
        echo htmlspecialchars($ko);
        echo '</td><td>';

        $ko1 = xmlrpc_alt_encode($val);
        if (preg_replace(array('/ /', "/\n/", "/\r/", '/encoding="[^"]+"/', '!<data/>!', '!<string/>!', '!<params/>!'), array('', '', '', '', '<data></data>', '<string></string>', '<params></params>'), $ko) != str_replace(array(' ', "\n", "\r"), array('', '', ''), $ko1))
		{
          echo '<b>'.htmlspecialchars($ko1)."</b>\n";
        }
        echo '</td><td>';

        $ok = xmlrpc_decode($ko);
        var_dump($ok);
        echo '</td><td>';

        $ok1 = xmlrpc_alt_decode($ko1);
        if ($ok !== $ok1)
		{
		  echo '<b>'; var_dump($ok1); echo "</b>";
		}
        echo '</td><td>';

        $ok = xmlrpc_encode_request('hello', $val);
        echo htmlspecialchars($ok);
        echo '</td><td>';

        $ok1 = xmlrpc_alt_encode_request('hello', $val);
        if (preg_replace(array('/ /', "/\n/", "/\r/", '/encoding="[^"]+"/', '!<data/>!', '!<string/>!', '!<params/>!'), array('', '', '', '', '<data></data>', '<string></string>', '<params></params>'), $ok) != str_replace(array(' ', "\n", "\r"), array('', '', ''), $ok1))
		{
          echo '<b>'.htmlspecialchars($ok1)."</b>\n";
        }
        echo '</td><td>';

        $methodname = '';
        $ko = xmlrpc_decode_request($ok, $methodname);
        var_dump($ko);
        echo '</td><td>';

        $ko1 = xmlrpc_alt_decode_request($ok1, $methodname);
        if ($ko !== $ko1)
		{
		  echo '<b>'; var_dump($ko1); echo "</b>";
		}
        echo '</td><td>';

        //$ko = xmlrpc_decode_request('zzz'.$ok, $methodname);
        //echo  'DECODED BAD  : '; var_dump($ko);

        $ok = xmlrpc_encode_request(null, $val); // methodresponse generated
        echo htmlspecialchars($ok);
        echo '</td><td>';

        $ok1 = xmlrpc_alt_encode_request(null, $val);
        if (preg_replace(array('/ /', "/\n/", "/\r/", '/encoding="[^"]+"/', '!<data/>!', '!<string/>!', '!<params/>!'), array('', '', '', '', '<data></data>', '<string></string>', '<params></params>'), $ok) != str_replace(array(' ', "\n", "\r"), array('', '', ''), $ok1))
		{
          echo '<b>'.htmlspecialchars($ok1)."</b>\n";
        }
        echo '</td><td>';

        $methodname = '***';
        $methodname1 = '***';
        $ko = xmlrpc_decode_request($ok, $methodname);
        var_dump($ko);
        echo '</td><td>';

        $ko1 = xmlrpc_decode_request($ok1, $methodname1);
        if ($ko !== $ko1)
		{
		  echo '<b>'; var_dump($ko1); echo "</b>";
		}
		echo "</td></tr>\n";
      }
      @fclose($v3);

    }

  }

  $test = new apitests();
  $test->testEncoding();

?>
</body>
</html>