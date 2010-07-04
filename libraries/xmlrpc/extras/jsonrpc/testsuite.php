<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
<head>
<title>JSONRPC Tetsuite</title>
<style type="text/css">
body { font-family: courier; font-size: small; }
td { border: 1px solid silver; vertical-align: top; }
.diff { font-weight: bold; }
/* distinguish bad cases, good cases, borderline */
/*
#cell_2_6 #cell_5_13, #cell_6_13, #cell_5_12, #cell_6_12, #cell_2_14, #cell_2_18, #cell_5_18, #cell_2_20, #cell_5_20 { background-color: yellow; }
#cell_5_14, #cell_6_14 { background-color: red; }
*/
</style>
<script type="text/javascript">
// dump var info using the same format as php var_dump
function print_phpstyle(aVal) {
  if (aVal == undefined)
  {
    document.writeln('undefined');
  }
  else
  {
    var t = typeof(aVal);
    if (t == 'string')
    {
      document.writeln('string('+ new String(aVal).length +') "'+aVal+'"');
    }
    else
    if (t == 'number')
    {
      document.writeln('number('+aVal+')');
    }
    else
    if (t == 'boolean')
    {
      document.writeln('bool('+aVal+')');
    }
    else
    {
      // really simple (aka stoopid) test to tell apart arrays from objects
      var len = aVal.length;
      if (len == undefined)
      {
        // object iterator
        var i=0;
        for (aProp in aVal) { i++; }
        document.writeln('object(stdClass) ('+i+') {');
        for (aProp in aVal)
        {
          document.writeln(' ['+aProp+']=> ');
          print_phpstyle(aVal[aProp]);
        }
        document.writeln(' }');
      }
	  else
      {
        // array iterator
        document.writeln('array('+len+') {'); //+anArray.toString()+' }' );
        for (var i=0; i < aVal.length; i++)
        {
          document.writeln(' ['+i+']=> ');
          print_phpstyle(aVal[i]);
          //document.writeln(', ');
        }
        document.writeln(' }');
      }
    }
  }
}
</script>
</head>
<body>
<?php

//  require_once 'phpunit.php';
//  require_once 'PHPUnit/TestDecorator.php';
  require_once 'xmlrpc.inc';
  require_once 'jsonrpc.inc';
  require_once 'json_extension_api.inc';
  @include('JSON.php');
  @include(getcwd().'/Zend/Zend_Json.php'); // renamed file: a bit strange for everybody to use json.php isn't it?

  class apitests
  {
    var $pear_json = false;

    /// strings used in json parsing test
    var $teststrings = array(

'',
'1',
'true',
'null',
'"hello"',
'not a value',
'[]',
'[1]',
'[1.1]',
'[-1E+4]',
'[100.0e-2]',
'[.5]',
'[5.]',
'[.]',
'[5..5]',
'[10e]',
'[e10]',
'[010e2]',
'[010.2]',
'[010]',
'[0xFF]',
'[0xff]',
'[true]',
'[TRUE]',
'[null]',
'[NULL]',
'[""]',
'["a"]',
' [ "a" ] ',

// extra commas in array: ecma calls them 'elided elements'
'[1,]',
'[,]',
'[,1]',
'[1,,1]',

// comments
"// comment here\n[1]",
"[// comment here\n1]",
"[1// comment here\n]",
"[1]// comment here\n",

"/*comment here*/[1]",
"[/*comment here*/1]",
"[1/*comment here*/]",
"[1]/*comment here*/",

"/**/[1]",
"//\n[1]",

"[1\n// comment here\n]",
"[\n// comment here\n1]",
"[1]// comment here", // NB: valid for ie and ff...
"[1]\n// comment here",
"[\"a\n// this is a comment\nb\"]",
"[\"a // this is not a comment b\"]",

// quotes tricks
"['a']",
"[\"a]",
"[a\"]",
"['a\"]",
"[\"a']",
"[']",
"['']",
"[''']",
"[\"]",
"[\"\"\"]",
"[\"'\"]",
"['\"']",
"[\"\\'\"]",
"[\"\\\\'\"]",
"[\"\\\"\"]",
"[\"\\\"]",
"[\"\\\\\"]",
"[\"\\\\\\\"]",


// line breaks
"\n[\"a\"]",
"[\n\"a\"]",
"[\"a\n\"]",
"[\"a\"\n]",
"[\"a\"]\n",

// char encoding
"[\"\\u0041\\u00DC\"]",
"[\"\\b\\t\\f\\v\\r\\n\"]",
"[\"\\b\\t\\f\\r\\n\"]",
"[\"\\x41\\xDC\"]",

// too many elements inside array
'[ ] [ ]',
'["a" "b"]',
'[1 2]',


// objects
'[{}]',
'[ { } ]',
'[{1}]',
'[{1:1}]',
'[{:1}]',
'[{"1":}]',
'[{"1":1}]',
'[{"":1}]',
'[{"a":}]',
'[{"a":1}]',
'[{"true":1}]',
'[{true:1}]', // rejected by FF, okay the lib
'[{null:1}]',
'[{a "a":1}]', // rejected by FF, okay the lib
'[{"a":1"a"}]',
'[{a b:"a"}]', // rejected by FF, okay the lib
'[{a b:a b}]',
'[{a 1:a 1}]',
'[{a:b:c}]',
'[/*comment here*/{"1":1}]',
'[{/*comment here*/"1":1}]',
'[{"1"/*comment here*/:1}]',
'[{"1":/*comment here*/1}]',
'[{"1":1/*comment here*/}]',
'[{"1":1}/*comment here*/]',
);

    function apitests()
    {
      $v1 = fopen(__FILE__, 'r');
      /// values used for testing differences v.a.v. json extension
      $this->testvals = array(
        true,
        false,
        0,
        1,
        1.0, // php native encoding strips the .0
        1.1,
        '',
        null,
        '1',
        '20060101T12:00:00',
        utf8_encode('Günter, Elène'),
        'Günter, Elène', // KO: we encode it somehow, PHP stops on the first string
        $v1,
        base64_encode('hello'), // string
        new jsonrpcmsg('dummy'),
        new xmlrpcval(),
        array(),
        array(utf8_encode('Günter, Elène')),
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
        array('faultCode' => 666, 'faultString' => array('hello' => 'world'))
        );

      if (class_exists('Services_JSON'))
	    $this->pear_json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    }

	function valdiff($val, $ref)
	{
		//$out = var_export($val, true);
		///@todo better testing of equality for objects
        ob_start();
        var_dump($val);
        $val=ob_get_contents();
	    ob_clean();
        var_dump($ref);
        $ref=ob_get_contents();
        ob_end_clean();
        $val = preg_replace('/object\(stdClass\)#[0-9]+/', 'object(stdClass)#xx', $val);
        $ref = preg_replace('/object\(stdClass\)#[0-9]+/', 'object(stdClass)#xx', $ref);
		if (($ref === 'NULL') || $val !== $ref)
		{
			echo '<span class="diff">'.$val.'</span>';
		}
		else
		  //echo htmlspecialchars($out);
		  echo $val;
	}

    function testDecoding()
    {

      echo "<h1>String decoding tests</h1><table>\n<tr><th>&nbsp;</th><th>Value</th><th>Decoded</th><th>JS dec.</th><th>Native dec.</th><th>Pear dec.</th><th>Zend dec.</th></tr>\n";

      foreach($this->teststrings as $i => $test)
      {

        echo "<tr><td id=\"cell_0_$i\">$i</td><td id=\"cell_1_$i\">"; var_dump($test);
        echo "</td><td id=\"cell_2_$i\">";
        $ok = json_parse($test, true);
        if ($ok)
        {
          var_dump($GLOBALS['_xh']['value']);
        }
        else
        {
          $GLOBALS['_xh']['value'] = null;
          echo "<span class=\"diff\">NULL</span><br/>'"; echo htmlspecialchars($GLOBALS['_xh']['isf_reason'])."'";
        }
        echo "</td><td id=\"cell_3_$i\">";
        $ok = $GLOBALS['_xh']['value'];
?>
<script language="javascript" type="text/javascript">
var anArray = eval('<?php echo str_replace(array("\\", "'"), array("\\\\", "\'"), $test); ?>');
if (anArray == undefined)
  document.writeln ('<span class="diff">NULL</span>');
else
  print_phpstyle(anArray);
</script>
<?php
        echo "</td><td id=\"cell_4_$i\">";

        if (extension_loaded('json'))
        {
	      $okk = json_decode($test);
          $this->valdiff($okk, $ok);
        }
        echo "</td><td id=\"cell_5_$i\">";

        if ($this->pear_json)
        {
          $okkk = $this->pear_json->decode($test);
          //if (Services_JSON::isError($okkk))
		  //{
          //  $okkk = NULL;
          //}
          $this->valdiff($okkk, $ok);
        }
        echo "</td><td id=\"cell_6_$i\">";

        if (class_exists('Zend_Json'))
        {
          try
		  {
		    $msg = '';
            $okkkk = Zend_Json::decode($test);
          }
		  catch (Exception $e)
		  {
		    $okkkk = NULL;
		    $msg = "<br/>\n'".$e->getMessage()."'";
		  }
          //if (Services_JSON::isError($okkk))
		  //{
          //  $okkk = NULL;
          //}
          $this->valdiff($okkkk, $ok);
          echo $msg;
        }
        echo "</td></tr>\n";
      }
      echo "</table>\n";
    }

    function testEncoding()
    {

      echo "<h1>Values encoding tests</h1><table>\n<tr><th>&nbsp;</th><th>Value</th><th>Encoded</th><!--<th>JS dec.</th>--><th>Native enc.</th><th>Pear enc.</th><th>Zend enc.</th></tr>\n";

      foreach($this->testvals as $i => $test)
      {

        echo "<tr><td id=\"cell_0_$i\">$i</td><td id=\"cell_1_$i\">"; var_dump($test);
        echo "</td><td id=\"cell_2_$i\">";
        $jsval = php_jsonrpc_encode($test, array());
        $jsval = $jsval->serialize();
        //if ($ok)
        //{
          var_dump($jsval);
        //}
        //else
        //{
        //  $GLOBALS['_xh']['value'] = null;
        //  echo "<b>NULL</b><br/>'"; echo htmlspecialchars($GLOBALS['_xh']['isf_reason'])."'";
        //}
        //echo "</td><td id=\"cell_3_$i\">";
?>
<!--
<script language="javascript" type="text/javascript">
var anArray = eval('<?php echo is_object($test) ? '' : str_replace(array("\\", "'"), array("\\\\", "\'"), $test); ?>');
if (anArray == undefined)
  document.writeln ('<span class="diff">NULL</span>');
else
  print_phpstyle(anArray);
</script>
-->
<?php
        echo "</td><td id=\"cell_4_$i\">";
        if (extension_loaded('json'))
        {
          $okk = json_encode($test);
          $this->valdiff($okk, $jsval);
        }
        echo "</td><td id=\"cell_5_$i\">";

        if ($this->pear_json)
        {
          $okkk = $this->pear_json->encode($test);
          if (Services_JSON::isError($okkk))
		  {
            $okkk = NULL;
          }
          $this->valdiff($okkk, $jsval);
        }
        echo "</td><td id=\"cell_6_$i\">";

        if (class_exists('Zend_Json'))
        {
          try
		  {
            $okkkk = Zend_Json::encode($test);
          }
		  catch (Exception $e)
		  {
		    $okkkk = NULL;
		  }
          //if (Services_JSON::isError($okkk))
		  //{
          //  $okkk = NULL;
          //}
          $this->valdiff($okkkk, $jsval);
        }
        echo "</td></tr>\n";
      }
      echo "</table>\n";
    }

    function testExtensionAPI()
	{
      echo "<h1>Extension API emulation tests</h1>\n<table>\n<tr><th></th><th>Original</th><th>Native Encoded</th><th>Encoded</th><th>Native Decoded</th><th>Decoded</th><th>Native Dec. as obj</th><th>Dec. as obj</th></tr>\n";

      foreach($this->testvals as $i => $val)
	  {
        echo "<tr><td>$i</td><td>"; var_dump($val);
	    echo "</td><td>";
	    if (extension_loaded('json'))
        {
          $j1 = json_encode($val);
          var_dump($j1);
          echo "</td><td>";
	      //var_dump($val);
          $j2 = json_alt_encode($val);
		  if ($j1 !== $j2) {
            echo '<b>'; var_dump($j2); echo "</b>\n";
          }
          else
            var_dump($j2);
        }
        else {
          echo "</td><td>";
          $j2 = json_encode($val);
          var_dump($j2);
        }
        echo "</td><td>";
        if (extension_loaded('json'))
        {
          $v1 = json_decode($j1, true);
          $e1 = function_exists('json_last_error') ? json_last_error() : 0;
		  var_dump($v1);
		  echo "</td><td>";
          $v2 = json_alt_decode($j2, true);
          $e2 = json_alt_last_error();
          if ($v1 !== $v2) {
            echo '<b>'; var_dump($v2); echo "</b>\n";
          }
          else
            var_dump($v2);
          if ($e1 != $e2)
          {
            echo "<br/><b>LAST ERROR: $e1 != $e2</b>";
          }
        }
        else
        {
		  echo "</td><td>";
          $v2 = json_decode($j2, true);
          var_dump($v2);
        }
        echo "</td><td>";
        if (extension_loaded('json'))
        {
          $v3 = json_decode($j1, false);
          $e3 = function_exists('json_last_error') ? json_last_error() : 0;
          if ($v1 !== $v3 && !is_array($v1)) {
            echo '<b>'; var_dump($v3); echo "</b>\n";
          }
          else
            var_dump($v3);
          echo "</td><td>";
          $v4 = json_alt_decode($j2, false);
          $e4 = json_alt_last_error();
          echo $this->valdiff($v4, $v3);
          /*if ($v3 !== $v4) {
            //$out = var_export($v4, true);
            echo '<b>'; var_dump($v4); echo "</b>\n";
          }
          else
            var_dump($v4);*/
          if ($e3 != $e4)
          {
            echo "<br/><b>LAST ERROR: $e3 != $e4</b>";
          }
        }
        else
        {
          echo "</td><td>";
          $v4 = json_decode($j2, false);
          var_dump($v4);
        }
        /*$v1 = json_decode('['.$j1.']', true);
        $v2 = json_alt_decode('['.$j2.']', true);
        echo "</td><td>";
        echo 'Encoded as array: ';var_dump($v1);
        echo "</td><td>";
        if ($v1 !== $v2) {
          echo '<b>'; var_dump($v2); echo "</b>\n";
        }
        $v1 = json_decode($val, true);
        $v2 = json_alt_decode($val, true);
        echo "</td><td>";
        echo 'Decoded val: ';var_dump($v1);
        echo "</td><td>";
        if ($v1 !== $v2) {
          echo '<b>'; var_dump($v2); echo "</b>\n";
        }*/
        echo "</td></tr>\n";
      }
      echo "</table>\n";
    }
  }

  $test = new apitests();
  $test->testDecoding();
  $test->testEncoding();
  $test->testExtensionAPI();
?>
</body>