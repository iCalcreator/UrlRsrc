<?php
/**
 * UrlRsrc - fetch an URL (file) resource result
 *
 * This file is part of UrlRsrc.
 *
 * @author    Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @copyright 2020-2024 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * @link      https://kigkonsult.se
 * @license   Subject matter of licence is the software UrlRsrc.
 *            The above copyright, link and this licence notice shall be
 *            included in all copies or substantial portions of the UrlRsrc.
 *
 *            UrlRsrc is free software: you can redistribute it and/or modify
 *            it under the terms of the GNU Lesser General Public License as
 *            published by the Free Software Foundation, either version 3 of
 *            the License, or (at your option) any later version.
 *
 *            UrlRsrc is distributed in the hope that it will be useful,
 *            but WITHOUT ANY WARRANTY; without even the implied warranty of
 *            MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *            GNU Lesser General Public License for more details.
 *
 *            You should have received a copy of the GNU Lesser General Public License
 *            along with UrlRsrc. If not, see <https://www.gnu.org/licenses/>.
 */
declare( strict_types = 1 );
namespace Kigkonsult\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UrlRsrcTest extends TestCase
{

    /**
     * getContentTest1 provider
     */
    public function getContentTest1Provider() : array
    {

        $dataArr = [];

        $dataArr[] = [
            111,
            'http://schemas.xmlsoap.org/ws/2004/08/addressing/',
            null,
            null,
            'schema',
        ];

        $dataArr[] = [
            112,
            'http://schemas.xmlsoap.org/ws/2004/08/addressing/',
            [],
            [],
            'schema',
        ];

        $dataArr[] = [
            113,
            'http://schemas.xmlsoap.org/ws/2004/08/addressing/',
            [],
            [
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ]
            ],
            'schema',
        ];

        $dataArr[] = [
            131,
            'https://curl.haxx.se/ca/cacert.pem',
            null,
            [ CURLOPT_HTTPHEADER  => ['Accept: */*' ]],
            null,
        ];

        $dataArr[] = [
            143,
            'https://duckduckgo.com',
            [
                'q'  => 'ethereum',
                't'  => 'ffsb',
                'ia' => 'cryptocurrency'
            ],
            [
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ]
            ],
            'html',
        ];


        return $dataArr;
    }

    /**
     * @test
     * @dataProvider getContentTest1Provider
     * @param int    $case
     * @param string $url
     * @param array  $urlArgs
     * @param array  $curlOpts
     * @param string $expPart
     */
    public function getContentTest1(
        int $case,
        string $url,
        $urlArgs,
        $curlOpts,
        $expPart = null
    )
    {
//      echo 'start ' . $case . PHP_EOL;

        $content = UrlRsrc::getContent( $url, $urlArgs, $curlOpts, $sizeDownload, $time );

        $this->assertNotEmpty(
            $content,
            $case . '-1 empty result'
        );
        if( ! empty( $expPart )) {
            $this->assertTrue(
                ( false !== strpos( $content, $expPart )),
                $case . '-2 can\'t find \'' . $expPart . '\' in ' . PHP_EOL . $content
            );
        }
        $this->assertTrue(
            ( ! empty( $sizeDownload ) && is_numeric( $sizeDownload )),
            $case . '-3 NO size'
        );
        $this->assertTrue(
            ( ! empty( $time ) && is_float( $time )),
            '-4 NO time'
        );

    }

    /**
     * @test
     */
    public function exceptionTest2()
    {
        try {
            $content = UrlRsrc::getContent( 'this is not an URL' );
            $this->assertTrue( false, '21 NO exception !!' );
        }
        catch( InvalidArgumentException $e ) {
            $this->assertTrue( true );
        }
    }

    /**
     * @test
     */
    public function exceptionTest3()
    {
        try {
            $content = UrlRsrc::getContent(
                'http://schemas.xmlsoap.org/ws/2004/08/addressing/',
                null,
                [ 'crazy' => 'option' ]
            );
            $this->assertTrue( false, '31 NO exception !!' );
        }
        catch( InvalidArgumentException|RuntimeException $e ) {
            $this->assertTrue( true );
        }
    }

    /**
     * @test
     */
    public function exceptionTest4()
    {
        try {
            $content = UrlRsrc::getContent(
                'http://www.fakeDomain.com'
            );
            $this->assertTrue( false, '41 NO exception !!' );
        }
        catch( RuntimeException $e ) {
            $this->assertTrue( true );
        }
    }

    /**
     * @ test
     */
    public function displayConstantsTest()
    {
        $this->assertTrue( true );

        echo 'CURLOPT_FAILONERROR    : ' . CURLOPT_FAILONERROR    . PHP_EOL;
        echo 'CURLOPT_FOLLOWLOCATION : ' . CURLOPT_FOLLOWLOCATION . PHP_EOL;
        echo 'CURLOPT_FRESH_CONNECT  : ' . CURLOPT_FRESH_CONNECT  . PHP_EOL;
        echo 'CURLOPT_USERAGENT      : ' . CURLOPT_USERAGENT      . PHP_EOL;
        echo 'CURLOPT_HTTPHEADER     : ' . CURLOPT_HTTPHEADER     . PHP_EOL;
        echo 'CURLOPT_RETURNTRANSFER : ' . CURLOPT_RETURNTRANSFER . PHP_EOL;
        echo 'CURLOPT_CONNECTTIMEOUT : ' . CURLOPT_CONNECTTIMEOUT . PHP_EOL;
        echo 'CURLOPT_TIMEOUT        : ' . CURLOPT_TIMEOUT        . PHP_EOL;
        echo 'CURLOPT_SSL_VERIFYPEER : ' . CURLOPT_SSL_VERIFYPEER . PHP_EOL;
        echo 'CURLOPT_SSL_VERIFYHOST : ' . CURLOPT_SSL_VERIFYHOST . PHP_EOL;

        echo 'PHP_URL_SCHEME   : ' . PHP_URL_SCHEME . PHP_EOL;
        echo 'PHP_URL_HOST     : ' . PHP_URL_HOST . PHP_EOL;
        echo 'PHP_URL_PORT     : ' . PHP_URL_PORT . PHP_EOL;
        echo 'PHP_URL_USER     : ' . PHP_URL_USER . PHP_EOL;
        echo 'PHP_URL_PASS     : ' . PHP_URL_PASS . PHP_EOL;
        echo 'PHP_URL_PATH     : ' . PHP_URL_PATH . PHP_EOL;
        echo 'PHP_URL_QUERY    : ' . PHP_URL_QUERY . PHP_EOL;
        echo 'PHP_URL_FRAGMENT : ' . PHP_URL_FRAGMENT . PHP_EOL;
    }
}
