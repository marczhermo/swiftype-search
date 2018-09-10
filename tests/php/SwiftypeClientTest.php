<?php
namespace Marcz\Swiftype\Tests;

use SapphireTest;
use TestOnly;
use Marcz\Swiftype\SwiftypeClient;
use GuzzleHttp\Ring\Client\CurlHandler;
use GuzzleHttp\Ring\Client\MockHandler;
use GuzzleHttp\Stream\Stream;
use Director;

class SwiftypeClientTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();
        if (!defined('SS_SWIFTYPE_END_POINT')) {
            define('SS_SWIFTYPE_END_POINT', 'http://api.swiftype.com/api/v1/');
        }
        if (!defined('SS_SWIFTYPE_AUTH_TOKEN')) {
            define('SS_SWIFTYPE_AUTH_TOKEN', 'SS_SWIFTYPE_AUTH_TOKEN');
        }
    }

    protected function fetchMockedResponse($data = [], $status = 200)
    {
        return new MockHandler(
            [
                'status' => $status,
                'body' => Stream::factory(new FakeStreamArray($data))
            ]
        );
    }

    public function testCreateClient()
    {
        $client = new SwiftypeClient;
        $curlClient = $client->createClient();

        $this->assertInstanceOf(CurlHandler::class, $client->createClient());
        $this->assertEquals($curlClient, $client->createClient());
    }

    public function testInitIndex()
    {
        $client = new SwiftypeClient;
        $rawQuery = $client->initIndex('index_name');
        $expected = [
            'http_method'   => 'GET',
            'uri'           => '/api/v1/',
            'headers'       => [
                'host'  => ['api.swiftype.com'],
                'Content-Type' => ['application/json'],
            ],
            'client'      => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ]
        ];

        $this->assertEquals($expected, $rawQuery);
    }

    public function testCreateIndex()
    {
        $client = new SwiftypeClient;

        $client->setClientAPI($this->fetchMockedResponse([['name' => 'myproducts']]));

        $this->assertTrue($client->createIndex('MyProducts'));
    }

    public function testHasEngine()
    {
        $client = new SwiftypeClient;
        $data = ['auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN')];
        $expected = [
            'http_method' => 'GET',
            'uri' => '/api/v1/engines.json',
            'headers' => [
                'host' => ['api.swiftype.com'],
                'Content-Type' => ['application/json'],
            ],
            'client' => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ],
            'body' => json_encode($data, JSON_PRESERVE_ZERO_FRACTION)
        ];

        $client->setClientAPI($this->fetchMockedResponse([['name' => 'myproducts']]));

        $this->assertTrue($client->hasEngine('MyProducts'));
        $this->assertEquals($expected, $client->sql());
    }

    public function testGetDocumentTypes()
    {
        $client = new SwiftypeClient;
        $data = ['auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN')];
        $expected = [
            'http_method' => 'GET',
            'uri' => '/api/v1/engines/myproducts/document_types.json',
            'headers' => [
                'host' => ['api.swiftype.com'],
                'Content-Type' => ['application/json'],
            ],
            'client' => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ],
            'body' => json_encode($data, JSON_PRESERVE_ZERO_FRACTION)
        ];

        $client->setClientAPI($this->fetchMockedResponse([['name' => 'myproducts']]));

        $this->assertEquals(
            [['name' => strtolower('MyProducts')]],
            $client->getDocumentTypes('MyProducts')
        );
        $this->assertEquals($expected, $client->sql());
    }

    public function testCreateEngine()
    {
        $client = new SwiftypeClient;
        $data = [
            'auth_token' => 'SS_SWIFTYPE_AUTH_TOKEN',
            'engine' => ['name' => 'myproducts']
        ];
        $expected = [
            'http_method' => 'POST',
            'uri' => '/api/v1/engines.json',
            'headers' => [
                'host' => ['api.swiftype.com'],
                'Content-Type' => ['application/json'],
            ],
            'client' => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ],
            'body' => json_encode($data, JSON_PRESERVE_ZERO_FRACTION)
        ];

        $client->setClientAPI($this->fetchMockedResponse([['name' => 'myproducts']]));

        $this->assertTrue($client->createEngine('MyProducts'));
        $this->assertEquals($expected, $client->sql());
    }

    public function testCreateDocumentType()
    {
        $client = new SwiftypeClient;
        $data = [
            'auth_token' => 'SS_SWIFTYPE_AUTH_TOKEN',
            'document_type' => ['name' => 'myproducts']
        ];
        $expected = [
            'http_method' => 'POST',
            'uri' => '/api/v1/engines/myproducts/document_types.json',
            'headers' => [
                'host' => ['api.swiftype.com'],
                'Content-Type' => ['application/json'],
            ],
            'client' => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ],
            'body' => json_encode($data, JSON_PRESERVE_ZERO_FRACTION)
        ];

        $client->setClientAPI($this->fetchMockedResponse([['name' => 'myproducts']]));

        $this->assertTrue($client->createDocumentType('MyProducts', 'MyProducts'));
        $this->assertEquals($expected, $client->sql());
    }

    public function getEnv($name)
    {
        if (Director::isDev() && Director::is_cli() && $name == 'SS_SWIFTYPE_AUTH_TOKEN') {
            return 'SS_SWIFTYPE_AUTH_TOKEN';
        }
        // return Environment::getEnv($name);
        return constant($name);
    }
}
