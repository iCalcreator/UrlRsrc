## urlRsrc

__fetch an URL (file) resource content__

urlRsrc is a Curl wrapper and implements a (no-cache) http GET request.

Output is a URL resource result string.

Throws InvalidArgumentException/RuntimeException on error (also http code >= 400).

No cookie or return headers managent, works out-of-the-box

###### Usage

``` php
<?php
namespace Kigkonsult\Http;

include __DIR__ . '/vendor/autoload.php';

/**
 * @param string $url            the url (file) resource
 *                               for missning scheme 'http' is used
 * @param array  $urlArgs        opt, *( urlArgKey => value )
 *                               if not empty, appended to url
 * @param array  $curlOpts       opt, *( curlOptConstant => value )
 *                               overwrites default (below) key value if key exists
 *                               The keys should be valid curl_setopt() constants or their integer equivalents.
 * @param int    $sizeDownload   hold (byte-)size of downloaded resource
 * @param float  $time           hold operation exec time (in seconds)
 * @return string
 * @throws InvalidArgumentException
 * @throws RuntimeException
 */
$result = UrlRsrc::getContent( 'example.com' );

```

###### Curl options

Default Curl options are

    * fail if HTTP return code >= 400
    CURLOPT_FAILONERROR    => true,

    * follow redirects
    CURLOPT_FOLLOWLOCATION => true,

    * use a NO-cached connection
    CURLOPT_FRESH_CONNECT  => true,

    * array of HTTP headers, default Accept everything
    CURLOPT_HTTPHEADER     => [ 'Accept: */*' ],
    * example : prefer html/xml...
    *    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'

    * get URL contents
    CURLOPT_RETURNTRANSFER => true,

    * max seconds to wait before connect timeout
    CURLOPT_CONNECTTIMEOUT => 60,

    * max seconds to wait before Curl execute timeout
    CURLOPT_TIMEOUT        => 60,

    * no check of common the names in the SSL peer certificate
    CURLOPT_SSL_VERIFYHOST => 0,

Note

 * CURLOPT_SSL_VERIFYPEER is (auto-)set depending on URL scheme; https gives ```true``` else ```false```
 * UrlRsrc is indended to work without other added Curl options but<br>
   some URLs may require some

Opt certificate directives :
 
 * CURLOPT_CAINFO         => __DIR__ . '/cacert.pem'<br>
   certificate file<br>
   download up-to-date from http://curl.haxx.se/docs/caextract.html

 * CURLOPT_CAPATH         => 'fullPath/to/certs/dir'<br>
   directory with certificates

How to (opt) implement *basic* authentication :
* CURLOPT_HTTPAUTH => CURLAUTH_BASIC
* CURLOPT_USERPWD  => sprintf( '%s:%s', $userName, $password )

CurlOpts argument array key value overwrites default (above) if key is set.

More info about Curl options at [php.net].


###### Support

For support use [github.com] UrlRsrc. Non-emergence support issues are, unless sponsored, fixed in due time.


###### Sponsorship

Donation using <a href="https://paypal.me/kigkonsult" rel="nofollow">paypal.me/kigkonsult</a> are appreciated.
For invoice, <a href="mailto:ical@kigkonsult.se">please e-mail</a>.


###### Installation

[Composer], from the Command Line:

``` php
composer require kigkonsult/urlrsrc:dev-master
```

Composer, in your `composer.json`:

``` json
{
    "require": {
        "kigkonsult/urlrsrc": "dev-master"
    }
}
```

Composer, acquire access
``` php
use Kigkonsult\Http;
...
include 'vendor/autoload.php';
```


Otherwise , download and acquire..

``` php
use Kigkonsult\Http\UrlRsrc;
...
include 'pathToSource/UrlRsrc/autoload.php';
```

###### License

This project is licensed under the LGPLv3 License


[Composer]:https://getcomposer.org/
[github.com]:https://github.com/iCalcreator/UrlRsrc
[php.net]:https://www.php.net/manual/en/function.curl-setopt.php
