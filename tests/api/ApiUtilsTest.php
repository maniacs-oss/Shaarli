<?php

namespace Shaarli\Api;

use Shaarli\Base64Url;


/**
 * Class ApiUtilsTest
 */
class ApiUtilsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Force the timezone for ISO datetimes.
     */
    public static function setUpBeforeClass()
    {
        date_default_timezone_set('UTC');
    }

    /**
     * Generate a valid JWT token.
     *
     * @param string $secret API secret used to generate the signature.
     *
     * @return string Generated token.
     */
    public static function generateValidJwtToken($secret)
    {
        $header = Base64Url::encode('{
            "typ": "JWT",
            "alg": "HS512"
        }');
        $payload = Base64Url::encode('{
            "iat": '. time() .'
        }');
        $signature = Base64Url::encode(hash_hmac('sha512', $header .'.'. $payload , $secret, true));
        return $header .'.'. $payload .'.'. $signature;
    }

    /**
     * Generate a JWT token from given header and payload.
     *
     * @param string $header  Header in JSON format.
     * @param string $payload Payload in JSON format.
     * @param string $secret  API secret used to hash the signature.
     *
     * @return string JWT token.
     */
    public static function generateCustomJwtToken($header, $payload, $secret)
    {
        $header = Base64Url::encode($header);
        $payload = Base64Url::encode($payload);
        $signature = Base64Url::encode(hash_hmac('sha512', $header . '.' . $payload, $secret, true));
        return $header . '.' . $payload . '.' . $signature;
    }

    /**
     * Test validateJwtToken() with a valid JWT token.
     */
    public function testValidateJwtTokenValid()
    {
        $secret = 'WarIsPeace';
        ApiUtils::validateJwtToken(self::generateValidJwtToken($secret), $secret);
    }

    /**
     * Test validateJwtToken() with a malformed JWT token.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformed()
    {
        $token = 'ABC.DEF';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with an empty JWT token.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformedEmpty()
    {
        $token = false;
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token without header.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformedEmptyHeader()
    {
        $token = '.payload.signature';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token without payload
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Malformed JWT token
     */
    public function testValidateJwtTokenMalformedEmptyPayload()
    {
        $token = 'header..signature';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token with an empty signature.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT signature
     */
    public function testValidateJwtTokenInvalidSignatureEmpty()
    {
        $token = 'header.payload.';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token with an invalid signature.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT signature
     */
    public function testValidateJwtTokenInvalidSignature()
    {
        $token = 'header.payload.nope';
        ApiUtils::validateJwtToken($token, 'foo');
    }

    /**
     * Test validateJwtToken() with a JWT token with a signature generated with the wrong API secret.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT signature
     */
    public function testValidateJwtTokenInvalidSignatureSecret()
    {
        ApiUtils::validateJwtToken(self::generateValidJwtToken('foo'), 'bar');
    }

    /**
     * Test validateJwtToken() with a JWT token with a an invalid header (not JSON).
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT header
     */
    public function testValidateJwtTokenInvalidHeader()
    {
        $token = $this->generateCustomJwtToken('notJSON', '{"JSON":1}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with a JWT token with a an invalid payload (not JSON).
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT payload
     */
    public function testValidateJwtTokenInvalidPayload()
    {
        $token = $this->generateCustomJwtToken('{"JSON":1}', 'notJSON', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with a JWT token without issued time.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT issued time
     */
    public function testValidateJwtTokenInvalidTimeEmpty()
    {
        $token = $this->generateCustomJwtToken('{"JSON":1}', '{"JSON":1}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with an expired JWT token.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT issued time
     */
    public function testValidateJwtTokenInvalidTimeExpired()
    {
        $token = $this->generateCustomJwtToken('{"JSON":1}', '{"iat":' . (time() - 600) . '}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test validateJwtToken() with a JWT token issued in the future.
     *
     * @expectedException \Shaarli\Api\Exceptions\ApiAuthorizationException
     * @expectedExceptionMessage Invalid JWT issued time
     */
    public function testValidateJwtTokenInvalidTimeFuture()
    {
        $token = $this->generateCustomJwtToken('{"JSON":1}', '{"iat":' . (time() + 60) . '}', 'secret');
        ApiUtils::validateJwtToken($token, 'secret');
    }

    /**
     * Test formatLink() with a link using all useful fields.
     */
    public function testFormatLinkComplete()
    {
        $indexUrl = 'https://domain.tld/sub/';
        $link = [
            'id' => 12,
            'url' => 'http://lol.lol',
            'shorturl' => 'abc',
            'title' => 'Important Title',
            'description' => 'It is very lol<tag>' . PHP_EOL . 'new line',
            'tags' => 'blip   .blop ',
            'private' => '1',
            'created' => \DateTime::createFromFormat('Ymd_His', '20170107_160102'),
            'updated' => \DateTime::createFromFormat('Ymd_His', '20170107_160612'),
        ];

        $expected = [
            'id' => 12,
            'url' => 'http://lol.lol',
            'shorturl' => 'abc',
            'title' => 'Important Title',
            'description' => 'It is very lol<tag>' . PHP_EOL . 'new line',
            'tags' => ['blip', '.blop'],
            'private' => true,
            'created' => '2017-01-07T16:01:02+00:00',
            'updated' => '2017-01-07T16:06:12+00:00',
        ];

        $this->assertEquals($expected, ApiUtils::formatLink($link, $indexUrl));
    }

    /**
     * Test formatLink() with only minimal fields filled, and internal link.
     */
    public function testFormatLinkMinimalNote()
    {
        $indexUrl = 'https://domain.tld/sub/';
        $link = [
            'id' => 12,
            'url' => '?abc',
            'shorturl' => 'abc',
            'title' => 'Note',
            'description' => '',
            'tags' => '',
            'private' => '',
            'created' => \DateTime::createFromFormat('Ymd_His', '20170107_160102'),
        ];

        $expected = [
            'id' => 12,
            'url' => 'https://domain.tld/sub/?abc',
            'shorturl' => 'abc',
            'title' => 'Note',
            'description' => '',
            'tags' => [],
            'private' => false,
            'created' => '2017-01-07T16:01:02+00:00',
            'updated' => '',
        ];

        $this->assertEquals($expected, ApiUtils::formatLink($link, $indexUrl));
    }
}
