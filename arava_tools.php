<?php
/**
 * Created by PhpStorm.
 * User: arava
 * Date: 9/29/2017
 * Time: 3:10 PM
 */

function funkit_setlog($title, $value)
{
    $vartype = gettype($value);
    $t = '';
    $f = fopen("funkit.log", "a+");
//  $t .= '$vartype - ' . $vartype . "\r\n";
    $t .= $vartype . " - ";

    if($vartype == 'string' OR $vartype == 'integer')
    {
        $t .= $title . ' - ' . $value . "\r\n\r\n";
    }
    elseif($vartype == 'array')
    {
        $t .= $title . "\r\n" . print_r($value, true) . "\r\n\r\n";
    }
    elseif($vartype == 'boolean')
    {
        if($value)
        {
            $t .= $title . ' - ' . 'TRUE' . "\r\n\r\n";
        }
        else
        {
            $t .= $title . ' - ' . 'FALSE' . "\r\n\r\n";
        }
    }
    elseif($vartype == 'double')
    {
        $t .= $title . ' - ' . $value . "\r\n\r\n";
    }
    elseif($vartype == 'object')
    {
        $temp = funkit_objectToArray($value);
        $t .= $title . "\r\n" . print_r($temp, true) . "\r\n\r\n";
    }
    elseif($vartype == 'resource')
    {
        $temp = stream_get_contents($value);
        $t .= $title . "\r\n" . print_r($temp, true) . "\r\n\r\n";
    }
    elseif($vartype == 'NULL')
    {
        $t .= $title . ' - ' . 'NULL' . "\r\n\r\n";
    }

    fwrite($f, $t);
    fclose($f);
}