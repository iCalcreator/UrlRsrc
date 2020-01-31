<?php
/**
 * UrlRsrc - fetch an URL (file) resource result
 *
 * Copyright 2020 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link <https://kigkonsult.se>
 * Support <https://github.com/iCalcreator/UrlRsrc>
 *
 * This file is part of UrlRsrc.
 *
 * UrlRsrc is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * UrlRsrc is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UrlRsrc.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace Kigkonsult\Http;

use InvalidArgumentException;
use RuntimeException;

use function curl_close;
use function curl_errno;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function filter_var;
use function is_null;
use function http_build_query;
use function microtime;
use function parse_url;
use function preg_replace_callback;
use function sprintf;
use function strncasecmp;
use function urldecode;
use function urlencode;
use function var_export;

class UrlRsrc
{

    /**
     * Version
     *
     * @var string
     */
    private static $VERSION = 'kigkonsult.se UrlRsrc 1.0';

    /**
     * Default cUrl options
     *
     * @var string[]
     * @link https://www.php.net/manual/en/function.curl-setopt.php
     */
    private static $CURLOPTS = [
        // fail if HTTP return code >= 400
        CURLOPT_FAILONERROR    => true,

        // follow redirects
        CURLOPT_FOLLOWLOCATION => true,

        // use a NO-cached connection
        CURLOPT_FRESH_CONNECT  => true,

        // array of HTTP headers, default Accept everything
        CURLOPT_HTTPHEADER     => [ 'Accept: */*' ],
        // prefer html/xml but...
        // 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'

        // get URL contents
        CURLOPT_RETURNTRANSFER => true,

        // max seconds to wait before connect timeout
        CURLOPT_CONNECTTIMEOUT => 60,

        // max seconds to wait before cUrl execute timeout
        CURLOPT_TIMEOUT        => 60,

        // ignore SSL errors or not, auto-set depending on url-scheme, https=>true
        // CURLOPT_SSL_VERIFYPEER => false,

        // certificate file
        // download up-to-date from http://curl.haxx.se/docs/caextract.html
        // CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
        // certificates directory
        // CURLOPT_CAPATH         => 'fullPath/to/certs/dir',

        // no check of common the names in the SSL peer certificate
        CURLOPT_SSL_VERIFYHOST => 0,
    ];

    /**
     * @var string
     */
    private $url = null;

    /**
     * @var resource
     */
    private $curlResource = null;

    /**
     * @var array
     */
    private $curlOpts = [];

    /**
     * @var string
     */
    private $result = null;

    /**
     * @var array
     */
    private $curlInfo = [];

    /**
     * class construct
     */
    public function __construct() {
        $this->curlOpts = self::$CURLOPTS;
    }

    /**
     * Return http resource (file) result, http GET factory method (using Curl)
     *
     * @param string $url            the url (file) resource
     *                               for missning scheme 'http' is used
     * @param array  $urlArgs        opt, *( urlArgKey => value )
     *                               if not empty, appended to url
     * @param array  $curlOpts       opt, *( curlOptConstant => value )
     *                               overwrites self::$CURLOPTS key value if key exists
     *                               The keys should be valid curl_setopt() constants or their integer equivalents.
     * @param int    $sizeDownload   hold (byte-)size of downloaded resource
     * @param float  $time           hold operation exec time (in seconds)
     * @return string
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @static
     */
    public static function getContent( $url, $urlArgs = [], $curlOpts = [], & $sizeDownload = 0, & $time = 0.0 ) {
        static $SIZEDOWNLOAD = 'size_download';
        $startTime = microtime( true );
        $factory   = new self();
        $factory->setUrl( $url, $urlArgs )
            ->setCurlOpts( $curlOpts )
            ->initCurlResource()
            ->setCurlhandlerOptions()
            ->curlExec();
        $sizeDownload = $factory->getCurlInfo( $SIZEDOWNLOAD );
        $time         = microtime( true ) - $startTime;
        return $factory->result;
    }

    /**
     * @param string $url
     * @param array  $urlArgs
     * @return static
     * @throws InvalidArgumentException
     * @access private
     */
    private function setUrl( $url, $urlArgs ) {
        $url = self::assureUrlScheme( $url );
        $url = self::getUrlWithAppendedParams( $url, $urlArgs );
        self::assertUrl( $url );
        $this->url = $url;
        $this->defaultCurlOptionsSslCheck();
        return $this;
    }

    private static $FMTERRURL = '(#%d) \'%s\' is not a valid url';

    /**
     * Set url scheme 'http' if missing
     *
     * @param string $url
     * @return string
     * @access private
     * @static
     */
    private static function assureUrlScheme( $url ) {
        static $HTTP  = 'http://';
        if( false === ( $scheme = parse_url( $url, PHP_URL_SCHEME ))) {
            throw new InvalidArgumentException( sprintf( self:: $FMTERRURL, 1, $url ));
        }
        return is_null( $scheme ) ? $HTTP . $url : $url;
    }

    /**
     * Return url with appended params
     *
     * @param string $url
     * @param array  $urlArgs
     * @return string
     * @access private
     * @static
     */
    private static function getUrlWithAppendedParams( $url, $urlArgs ) {
        static $Q     = '?';
        static $ET    = '&';
        static $EMPTY = '';
        if( empty( $urlArgs )) {
            return $url;
        }
        if( false === ( $query = parse_url( $url, PHP_URL_QUERY ))) {
            throw new InvalidArgumentException( sprintf( self:: $FMTERRURL, 2, $url ));
        }
        $appenChar = empty( $query ) ? $Q : $ET;
        return $url . $appenChar . http_build_query((array) $urlArgs, $EMPTY, $ET );
    }

    /**
     * Assert url
     *
     * @param string $url
     * @throws InvalidArgumentException
     * @access private
     * @static
     */
    private static function assertUrl( $url ) {
        static $SP0    = '';
        static $CSS    = '://';
        static $COLON  = ':';
        static $SCHEME = 'scheme';
        static $HOST   = 'host';
        static $PORT   = 'port';
        static $USER   = 'user';
        static $PASS   = 'pass';
        static $PATH   = 'path';
        $urlArr = self::mb_parse_url( $url );
        $urlTmp = $SP0;
        $schemeSet = $hostSet = false;
        if( isset( $urlArr[$SCHEME] ) && ! empty( $urlArr[$SCHEME] )) {
            $urlTmp    = $urlArr[$SCHEME] . $CSS;
            $schemeSet = true;
        }
        if( isset( $urlArr[$USER] ) && ! empty( $urlArr[$USER] )) {
            $urlTmp .= $urlArr[$USER];
        }
        if( isset( $urlArr[$PASS] ) && ! empty( $urlArr[$PASS] )) {
            $urlTmp .= $COLON . $urlArr[$PASS];
        }
        if( isset( $urlArr[$HOST] ) && ! empty( $urlArr[$HOST] )) {
            $urlTmp .= $urlArr[$HOST];
            $hostSet = self::hasInvalidCharacter( $urlArr[$HOST] ) ? false : true;
        }
        if( isset( $urlArr[$PORT] ) && ! empty( $urlArr[$PORT] )) {
            $urlTmp .= $COLON . $urlArr[$PORT];
        }
        if( isset( $urlArr[$PATH] ) && ! empty( $urlArr[$PATH] )) {
            $urlTmp .= $urlArr[$PATH];
            $hostSet = self::hasInvalidCharacter( $urlArr[$PATH] ) ? false : true;
        }
        if( false !== filter_var( $urlTmp, FILTER_VALIDATE_URL )) {
            return;
        }
        elseif( $schemeSet && $hostSet ) { // but accept utf8 chars
            return;
        }
        throw new InvalidArgumentException( sprintf( self:: $FMTERRURL, 4, $urlTmp ));
    }

    /**
     * UTF-8 aware parse_url() replacement.
     *
     * @return array
     * @link https://www.php.net/manual/en/function.parse-url.php#114817
     * @access private
     * @static
     */
    private static function mb_parse_url( $url ) {
        static $REGEX = '%[^:/@?&=#]+%usD';
        $enc_url = preg_replace_callback(
            $REGEX,
            function( $matches ) { return urlencode($matches[0] ); },
            $url
        );
        ;
        if( false === ( $parts = parse_url( $enc_url ))) {
            throw new InvalidArgumentException( sprintf( self:: $FMTERRURL, 5, $url ));
        }
        foreach( $parts as $name => $value ) {
            $parts[$name] = urldecode( $value );
        }
        return $parts;
    }

    /**
     * Return bool true if exclude-char if found in string
     *
     * @param string $value
     * @return bool
     * @static
     * @access private
     */
    private static function hasInvalidCharacter( $value ) {
        static $INVCHARS = [
            ' ',
            "<", ">" , "%", "#", '"',
            "{", "}", "|", '\\', "^", "[", "]", "`"
        ];
        foreach( $INVCHARS as $invChar ) {
            if( false !== strpos( $value, $invChar )) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set default curlOpts SSL_VERIFYPEER OFF if url scheme is 'http', https gives ON
     *
     * @access private
     */
    private function defaultCurlOptionsSslCheck() {
        static $HTTP  = 'http';
        static $HTTPS = 'https';
        if( false === ( $scheme = parse_url( $this->url, PHP_URL_SCHEME ))) {
            throw new InvalidArgumentException( sprintf( self:: $FMTERRURL, 6, $this->url ));
        }
        if(( false != $scheme ) && // also null
            ( 0 == strncasecmp( $HTTP, $scheme, 4 )) &&
            ( 0 != strncasecmp( $HTTPS, $scheme, 5 )) &&
            ! isset( $this->curlOpts[CURLOPT_SSL_VERIFYPEER] )) {
            $this->curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
        }
        elseif(( 0 == strncasecmp( $HTTPS, $scheme, 5 )) &&
            ! isset( $this->curlOpts[CURLOPT_SSL_VERIFYPEER] )) {
            $this->curlOpts[CURLOPT_SSL_VERIFYPEER] = true;
        }
    }

    /**
     * @return static
     * @throws RuntimeException
     */
    public function initCurlResource() {
        static $FMT = 'cUrl init error, url %s';
        if( false === ( $this->curlResource = curl_init( $this->url ))) {
            throw new RuntimeException( sprintf( $FMT, $this->url ));
        }
        return $this;
    }

    /**
     * Set cUrl options, overwrite if key exists, NO key assert
     *
     * @param array $curlOpts
     * @return static
     */
    public function setCurlOpts( $curlOpts ) {
        foreach((array) $curlOpts as $key => $value ) {
            $this->curlOpts[$key] = $value;
        }
        return $this;
    }

    /**
     * Set cUrl handler options
     *
     * @return static
     * @throws RuntimeException
     */
    public function setCurlhandlerOptions() {
        static $FMT = 'cUrl setOptions error, url %s, options : %s';
        if( false === curl_setopt_array( $this->curlResource, $this->curlOpts )) {
            throw new RuntimeException( sprintf( $FMT, $this->url, var_export( $this->curlOpts, true )));
        }
        return $this;
    }

    /**
     * @param string $key
     * @return string|array
     */
    public function getCurlInfo( $key = null ) {
        if( is_null( $key )) {
            return $this->curlInfo;
        }
        return $this->curlInfo[$key];
    }

    /**
     * @return string
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * cUrl exec and close
     *
     * For curl error codes, see https://curl.haxx.se/libcurl/c/libcurl-errors.html
     *
     * @return static
     */
    public function curlExec() {
        static $FMT1    = 'cUrl not initialized, url %s, cUrlOpts : %s';
        static $FMT7    = 'cUrl error (#%d) %s, url %s, cUrlOpts : %s, debugInfo : %s';
        if( ! is_resource( $this->curlResource )) {
            if( ! empty( $this->url )) {
                $this->initCurlResource();
            }
            else {
                throw new RuntimeException( sprintf( $FMT1, $this->url, var_export( $this->curlOpts, true )));
            }
        }
        curl_setopt( $this->curlResource, CURLOPT_USERAGENT, self::$VERSION );
        $this->result   = curl_exec( $this->curlResource );
        $this->curlInfo = curl_getinfo( $this->curlResource );
        $errno          = curl_errno( $this->curlResource );
        $errtxt         = curl_error( $this->curlResource );
        curl_close( $this->curlResource );
        if(( false === $this->result ) || ! empty( $errno )) {
            $msg = sprintf(
                $FMT7,
                $errno,
                $errtxt,
                $this->url,
                var_export( $this->curlOpts, true ),
                var_export( $this->curlInfo, true )
            );
            throw new RuntimeException( $msg );
        }
        return $this;
    }

}
