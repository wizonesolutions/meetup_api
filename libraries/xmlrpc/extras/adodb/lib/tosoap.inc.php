<?php
/**
* Helper functions to convert between ADODB recordset objects and SOAP values.
* Uses John Lim's AdoDB and NuSOAP libs
* 
* @author Gaetano Giunta
* @copyright (c) 2005-2009 Gaetano Giunta. All rights reserved.
* 
* @todo use wsdl object to ease serialization / deserialization ?
* @todo have an ADODB-tuned deserializer (i.e. use php decription of db native types
*       as alternative to JDBC - see adodb metatypes infrastructure for complete list)
*/

$tosoap_namespace = 'ExecSqlOnDb';

/// Decode a JDBC-standard DB type description into a php conversion function
/// @todo: add as 3d param the type length? (i.e. BIT[5] => array($bool, ...)
function JDBC2PHPType($jdbctype, $stringval)
{
  switch (strtoupper($jdbctype))
  {
    case 'TINYINT':
    case 'SMALLINT':
    case 'INTEGER':
    case 'BIGINT':
      return intval($stringval);
    case 'REAL':
    case 'DOUBLE':
    case 'FLOAT':
    case 'DECIMAL':
    case 'NUMERIC':
      return floatval($stringval);
    case 'BIT':	  
    case 'BOOLEAN':
      return $stringval ? true : false;
    default:
      return $stringval;
  }  
}

    /**
    * Include the main libraries
    */    
    require_once('nusoap.php');
//    require_once('adodb.inc.php');
            
    /**
    * Builds an xmlrpc struct value out of an AdoDB recordset
    */
    function rs2soapval(&$adodbrs) {
      global $tosoap_namespace;

        $header =& rs2soapval_header($adodbrs);
        $body =& rs2soapval_body($adodbrs);

        // put it all together and build final xmlrpc struct
        $soapval =& new soapval ( 'resultSet', 'Resultset', array(
                'RecordSetMetadata' => $header,
                'RecordSet' => $body
                ), null, $tosoap_namespace);

        return $soapval;

    }

    /**
    * Builds an xmlrpc struct value describing an AdoDB recordset
    */
    function rs2soapval_header($adodbrs)
    {
      global $tosoap_namespace;

        $numfields = $adodbrs->FieldCount();
        //$numrecords = $adodbrs->RecordCount();

        // build structure holding recordset information
        $fieldstruct = array();
        for ($i = 0; $i < $numfields; $i++) {
            $fld = $adodbrs->FetchField($i);
            $fieldarray = array();

            $fieldarray["order"] = $i;

            if (isset($fld->name))
                $fieldarray["columnName"] = $fld->name;

            if (isset($fld->type))
                $fieldarray["columnType"] = $fld->type;

            if (isset($fld->max_length))
                $fieldarray["maxLength"] = $fld->max_length;

            if (isset($fld->not_null))
                $fieldarray["isNullable"] = $fld->not_null ? "false" : "true";

            if (isset($fld->has_default))
                $fieldarray["hasDefault"] = $fld->has_default ? "true" : "false";

            if (isset($fld->default_value))
                $fieldarray["defaultValue"] = $fld->default_value;

            $fieldstruct[$i] =& new soapval ('recordSetMetaDataElement', 'RecordSetMetaDataElement',
              null, null, $tosoap_namespace, $fieldarray);
        }
        //$fieldcount =& new xmlrpcval ($numfields, "int");
        //$recordcount =& new xmlrpcval ($numrecords, "int");
        //$sql =& new xmlrpcval ($adodbrs->sql);

        $header =& new soapval ('recordSetMetaData', 'recordSetMetaData', $fieldstruct,
          null, $tosoap_namespace, array('fieldCount' => $numfields, 'recordCount' => $adodbrs->RecordCount()));

        return $header;
    }

    /**
    * Builds an xmlrpc struct value out of an AdoDB recordset
    * (data values only, no data definition)
    */
    function rs2soapval_body($adodbrs)
    {
      global $tosoap_namespace;

        $numfields = $adodbrs->FieldCount();

        // build structure containing recordset data
        $adodbrs->MoveFirst();
        $rows = array();
        while (!$adodbrs->EOF) {
            $columns = array();
            // This should work on all cases of fetch mode: assoc, num, both or default
            if ($adodbrs->fetchMode == 'ADODB_FETCH_BOTH' || count($adodbrs->fields) == 2 * $adodbrs->FieldCount())
                for ($i = 0; $i < $numfields; $i++)
                    $columns[$i] =& new soapval ('field', null, $adodbrs->fields[$i]);
            else
                foreach ($adodbrs->fields as $val)
                    $columns[] =& new soapval ('field', null, $val);

            $rows[] =& new soapval ('record', 'Record', $columns, null, $tosoap_namespace);

            $adodbrs->MoveNext();
        }
        $body =& new soapval ('recordSet', 'RecordSet', $rows, null, $tosoap_namespace);

        return $body;
    }
    
    /**
    * Returns an xmlrpc struct value as string out of an AdoDB recordset
    */    
    function rs2soapstring (&$adodbrs) {
        $xmlrpc = rs2soapval ($adodbrs);
        if ($xmlrpc)
          return $xmlrpc->serialize();
        else
          return null;
    }

    /**
    * Given a well-formed nusoap soap val object returns an AdoDB object.
    * Works fine if given as input the nusoap-decoded php array
    * 
    * @todo add some error checking on the input value
    */
    function soapval2rs (&$soapval) {
//timenow('decode_query_2_rs_start');
        $fields_array = array();
        $data_array = array();

        if (is_object($soapval))
        {
          $parser = new soap_parser($soapval->serialize());
          $data = $parser->buildVal(0);
        }
        else if (is_array($soapval))
          $data =& $soapval;
        else
          return false;

        //$data = $soapval->decode();
//var_dump($data);
        // rebuild column information  
        $header = $data['recordSetMetaData'];
        
        $numfields = $header['!fieldCount'];
        $numrecords = $header['!recordCount'];

        //$sqlstring = $header->structmem('sql');
        //$sqlstring = $sqlstring->scalarval();
        if ($numfields) {  // insert statements return a 0 x 0 recordset...
          $fieldinfo = $header['recordSetMetaDataElement'];
          // workaround for nusoap not being able to tell single child elements
          // from lists of elements
          //if (array_key_exists('!columnName' ,$fieldinfo)) {
          if ($numfields == 1) {
            $fieldinfo = array($fieldinfo);
          }
          for ($i = 0; $i < $numfields; $i++) {
              $temp = $fieldinfo[$i];
              $fld =& new ADOFieldObject();
              while (list($key,$value) = each($temp)) {
                  if ($key == "!columnName") $fld->name = $value;
                  if ($key == "!columnType") $fld->type = $value;
                  if ($key == "!maxLength") $fld->max_length = $value;
                  if ($key == "!isNullable") $fld->not_null = !$value;
                  if ($key == "!hasDefault") $fld->has_default = $value;
                  if ($key == "!defaultValue") $fld->default_value = $value;
              } // while
              $fields_array[] = $fld;
          } // for
        }
        // fetch recordset information into php array
        if ($numrecords)
        {
          $body = $data['recordSet']['record'];
          if ($numrecords == 1) {
            $body = array($body);
          }
          for ($i = 0; $i < $numrecords; $i++) {
            $data_array[$i]= array();
            $row = $body[$i]['field'];
            if ($numfields == 1) {
              $row = array($row);
            }
            for ($j = 0; $j < $numfields; $j++) {
                //$temp =& $xmlrpcrs_row->arraymem($j);
                $data_array[$i][$j] = JDBC2PHPType($fields_array[$j]->type, $row[$j]);
            } // for j
          } // for i
        }

        // finally build in-memory recordset object and return it
        $rs =& new ADORecordSet_array();
        $rs->InitArrayFields($data_array,$fields_array);
        return $rs;
//timenow('decode_query_2_rs');
    }

?>
