<?php

use GetNinja\Facebook\Sdk;
use GetNinja\Facebook\SdkException;
use Mockery as m;

class SdkTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \BaseFacebook
     */
    protected $sdkMock;

    /**
     * @var \GetNinja\Facebook\Sdk
     */
    protected $facebook;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    public $data;

    /**
     * End the testing
     */
    public function tearDown()
    {
        m::close();
    }

    /**
     * Prepare for testing
     */
    public function setUp()
    {
        $this->sdkMock = m::mock('\BaseFacebook');
        $this->sdkMock->shouldIgnoreMissing();

        $this->config = array(
            'appId'       => '491661414218957',
            'appSecret'   => 'dd0470f1919cfe0e1a52e81c6964bf16',
            'redirectUri' => 'http://localhost',
            'appPerms'    => 'email'
        );

        // http://graph.facebook.com/511174899
        $json = '{
            "id": "511174899",
            "name": "Luke Scalf",
            "first_name": "Luke",
            "last_name": "Scalf",
            "username": "luke.scalf",
            "gender": "male",
            "locale": "en_US"
        }';

        $this->data = json_encode($json, true);
    }

    /**
     * @expectedException GetNinja\Facebook\SdkException
     */
    public function testConstructorWithoutRedirectUriConfigVar()
    {
        $config = array();
        new GetNinja\Facebook\Sdk($this->sdkMock, $config);
    }

    public function testConstructorWithValidConfigVars()
    {
        new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);
    }

    public function testIsLoggedInWithoutLoggedInUser()
    {
        $this->sdkMock->shouldReceive(array(
            'getUser' => 0
        ));

        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $this->assertFalse($facebook->isLoggedIn());
    }

    public function testIsLoggedInWithLoggedInUser()
    {
        $this->sdkMock->shouldReceive(array(
            'getUser' => 1
        ));

        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $this->assertTrue($facebook->isLoggedIn());
        $this->assertEquals(1, $facebook->getId());
    }

    public function testGetLoginUrl()
    {
        $this->sdkMock->shouldReceive(array(
            'getLoginUrl' => 'localhost'
        ));

        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $this->assertEquals('localhost', $facebook->getLoginUrl());
    }

    public function testGetUserProfileData()
    {
        $this->sdkMock->shouldReceive('api')->with('/me')->andReturn($this->data);

        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $this->assertEquals($this->data, $facebook->getUserProfileData());
    }

    public function testTabAppMethods()
    {
        // http://developers.facebook.com/blog/post/462
        $signedRequestJson = '{
            "algorithm":"HMAC-SHA256",
            "expires":1297328400,
            "issued_at":1297322606,
            "oauth_token":"OAUTH_TOKEN",
            "app_data":"any_string_here",
            "page":{
                "id":119132324783475,
                "liked":true,
                "admin":false
            },
            "user":{
                "country":"us",
                "locale":"en_US"
            },
            "user_id":"USER_ID"
        }';

        $this->sdkMock->shouldReceive('getSignedRequest')->andReturn(json_decode($signedRequestJson, true));

        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $this->assertEquals('119132324783475', $facebook->getTabPageId());
        $this->assertEquals('any_string_here', $facebook->getTabAppData());
        $this->assertTrue($facebook->isTabPageLiked());
        $this->assertFalse($facebook->isTabPageAdmin());
    }

    public function testGetGivenPermissions()
    {
        $json = '{
            "data": [
                {
                    "installed": 1,
                    "email": 1,
                    "bookmarked": 1
                }
            ]
        }';

        $permissions = json_decode($json, true);

        $this->sdkMock->shouldReceive('api')->with('/me/permissions')->andReturn($permissions);
        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $expectedPermissions = array("installed", "email", "bookmarked");
        $this->assertEquals($expectedPermissions, $facebook->getGivenPermissions());
    }

    public function testGetFriends()
    {
        $json = '{
            "data": [
                {
                    "name": "Friend A",
                    "id": "1"
                },
                {
                    "name": "Friend B",
                    "id": "2"
                }
            ]
        }';

        $friends = json_decode($json, true);

        $this->sdkMock->shouldReceive('api')->with('/me/friends')->andReturn($friends);
        $facebook = new GetNinja\Facebook\Sdk($this->sdkMock, $this->config);

        $expectedFriends = array(
            array('name' => 'Friend A', 'id' => '1'),
            array('name' => 'Friend B', 'id' => '2')
        );

        $this->assertEquals($expectedFriends, $facebook->getFriends());
        $this->assertEquals(2, count($facebook->getFriendIds()));
    }
}
