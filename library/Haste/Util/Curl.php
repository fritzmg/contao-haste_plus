<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2017 Heimrich & Hannot GmbH
 *
 * @package haste_plus
 * @author  Dennis Patzer
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\Haste\Util;


class Curl
{
    public static function request($strUrl, array $arrRequestHeaders = [], $blnReturnResponseHeaders = false)
    {
        $objCurl = static::createCurlObject($strUrl);

        if (\Config::get('hpProxy'))
        {
            curl_setopt($objCurl, CURLOPT_PROXY, \Config::get('hpProxy'));
        }

        if (!empty($arrRequestHeaders))
        {
            static::setHeaders($objCurl, $arrRequestHeaders);
        }

        if ($blnReturnResponseHeaders)
        {
            curl_setopt($objCurl, CURLOPT_HEADER, true);
        }

        $strResponse   = curl_exec($objCurl);
        $intStatusCode = curl_getinfo($objCurl, CURLINFO_HTTP_CODE);
        curl_close($objCurl);

        if ($blnReturnResponseHeaders)
        {
            return static::splitResponseHeaderAndBody($strResponse, $intStatusCode);
        }
        else
        {
            return $strResponse;
        }
    }

    public static function postRequest($strUrl, array $arrRequestHeaders = [], array $arrPost = [], $blnReturnResponseHeaders = false)
    {
        $objCurl = static::createCurlObject($strUrl);

        if (\Config::get('hpProxy'))
        {
            curl_setopt($objCurl, CURLOPT_PROXY, \Config::get('hpProxy'));
        }

        if ($blnReturnResponseHeaders)
        {
            curl_setopt($objCurl, CURLOPT_HEADER, true);
        }

        if (!empty($arrRequestHeaders))
        {
            static::setHeaders($objCurl, $arrRequestHeaders);
        }

        if (!empty($arrPost))
        {
            curl_setopt($objCurl, CURLOPT_POST, true);
            curl_setopt($objCurl, CURLOPT_POSTFIELDS, http_build_query($arrPost));
        }

        $strResponse   = curl_exec($objCurl);
        $intStatusCode = curl_getinfo($objCurl, CURLINFO_HTTP_CODE);
        curl_close($objCurl);

        if ($blnReturnResponseHeaders)
        {
            return static::splitResponseHeaderAndBody($strResponse, $intStatusCode);
        }
        else
        {
            return $strResponse;
        }
    }

    public function createCurlObject($strUrl)
    {
        $objCurl = curl_init($strUrl);

        curl_setopt($objCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($objCurl, CURLOPT_TIMEOUT, 10);

        return $objCurl;
    }

    public function setHeaders($objCurl, array $arrHeaders)
    {
        $arrPrepared = [];

        foreach ($arrHeaders as $strName => $varValue)
        {
            $arrPrepared[] = $strName . ': ' . $varValue;
        }

        curl_setopt($objCurl, CURLOPT_HTTPHEADER, $arrPrepared);
    }

    public static function splitResponseHeaderAndBody($strResponse, $intStatusCode)
    {
        $arrHeaders = [];

        $intSplit  = strpos($strResponse, "\r\n\r\n");
        $strHeader = substr($strResponse, 0, $intSplit);
        $strBody   = str_replace($strHeader . "\r\n\r\n", '', $strResponse);

        foreach (explode("\r\n", $strHeader) as $i => $strLine)
        {
            if ($i === 0)
            {
                $arrHeaders['http_code'] = $intStatusCode;
            }
            else
            {
                list($strKey, $varValue) = explode(': ', $strLine);
                $arrHeaders[$strKey] = $varValue;
            }
        }

        return [$arrHeaders, trim($strBody)];
    }
}