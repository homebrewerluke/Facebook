<?php

namespace GetNinja\Facebook;

class Sdk
{
    /**
     * Facebook SDK Object
     *
     * @var Facebook
     */
    protected $sdk;

    /**
     * Application config vars
     *
     * @var array
     */
    protected $config;

    /**
     * Facebook User ID
     *
     * @var int
     */
    protected $userId;

    /**
     * User Profile Data
     *
     * @var array
     */
    protected $userProfile;

    /**
     * Redirect URI After Login
     *
     * @var string
     */
    protected $redirectUri;

    /**
     * Facebook app login url
     *
     * @var string
     */
    protected $loginUrl;

    /**
     * Signed Request
     *
     * @var array
     */
    protected $signedRequest;

    /**
     * Real-time updates subscription url
     *
     * @var string
     */
    protected $subscriptionUrl;

    /**
     * Create a new Sdk Instance
     *
     * @param \BaseFacebook $facebook
     * @param array        $config
     */
    public function __construct(\BaseFacebook $facebook, array $config)
    {
        $this->sdk = $facebook;
        $this->init($config);
    }

    /**
     * Initialize
     *
     * @param array $config
     */
    protected function init($config)
    {
        $this->setConfig($config);
        $this->setId();            // Set User Facebook Id
        $this->setSignedRequest();
        $this->setLoginUrl();
    }

    /**
     * Set the Facebook Config Vars.
     *
     * These are the necessary keys to init the Sdk:
     * - appId
     * - appSecret
     * - redirectUri
     *
     * Optional Keys:
     * - appPerms
     *
     * @param array $config
     */
    protected function setConfig($config)
    {
        if (!$this->areConfigVarsValid($config)) {
            throw new SdkException('Missing config vars');
        }
        $this->config = $config;
    }

    /**
     * Check if all necesary config keys exist
     *
     * @param  array   $config
     * @return boolean
     */
    protected function areConfigVarsValid($config)
    {
        if (!isset($config['appId']) || !isset($config['appSecret']) || !isset($config['redirectUri'])) {
            return false;
        }
        return true;
    }

    /**
     * Set default redirect uri after login
     */
    protected function setRedirectUri()
    {
        $this->redirectUri = $this->config['redirectUri'];
    }

    /**
     * Set user login url
     */
    protected function setLoginUrl()
    {
        // Set the default redirect uri
        $this->setRedirectUri();

        // Add request id's to the end of the login url
        if (!empty($_REQUEST['request_ids'])) {
            $this->redirectUri .= strpos($this->redirectUri, '?') === false ? '?' : '&';
            $this->redirectUri .= 'request_ids='.$_REQUEST['request_ids'];
        }

        // Set the login url
        $this->loginUrl = $this->sdk->getLoginUrl(array(
            'scope'        => (isset($this->config['appPerms']) ? $this->config['appPerms'] : ''),
            'redirect_uri' => $this->redirectUri
        ));
    }

    /**
     * Set Signed Request
     */
    protected function setSignedRequest()
    {
        $this->signedRequest = $this->sdk->getSignedRequest();
    }

    /**
     * Set Facebook User Id
     */
    protected function setId()
    {
        // User Id (0 if user is not logged in)
        $this->id = $this->sdk->getUser();
    }

    /**
     * Set user profile data (array)
     */
    protected function setUserProfileData()
    {
        try {
            // Call Api if profile data is empty
            if (null === $this->userProfile) {
                $this->userProfile = $this->sdk->api('/me');
            }
        } catch (Exception $e) {
            $this->userProfile = null;
            throw new SdkException($e);
        }
    }

    /**
     * Check user Facebook online status
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        return (boolean) $this->id;
    }

    /**
     * Get user Facebook Id
     *
     * @return int
     */
    public function getId()
    {
        return $this->isLoggedIn() ? $this->id : 0;
    }

    /**
     * Get login url
     *
     * @return string
     */
    public function getLoginUrl()
    {
        return $this->loginUrl;
    }

    /**
     * Get user all profile data
     *
     * @return array
     */
    public function getUserProfileData()
    {
        if (null === $this->userProfile) {
            $this->setUserProfileData();
        }
        return $this->userProfile;
    }

    /**
     * Get Facebook page Id of tab application
     *
     * @return int
     */
    public function getTabPageId()
    {
        return isset($this->signedRequest['page']['id']) ? $this->signedRequest['page']['id'] : 0;
    }

    /**
     * Get tab page app_data if set
     *
     * @return string
     */
    public function getTabAppData()
    {
        return !empty($this->signedRequest['app_data']) ? $this->signedRequest['app_data'] : false;
    }

    /**
     * Get user all permissions given to the application
     *
     * @return array
     */
    public function getGivenPermissions()
    {
        $data = $this->sdk->api('/me/permissions');
        if (empty($data)) {
            return array();
        }
        return array_keys($data['data'][0]);
    }

    /**
     * Get user friends (id-name array or just ids)
     *
     * @param  boolean $idsOnly
     * @return array
     */
    public function getFriends($idsOnly = false)
    {
        $friendsList = $this->sdk->api('/me/friends');

        if (!isset($friendsList['data'][0])) {
            return array();
        }

        if ($idsOnly) {
            $friends = array();
            foreach ($friendsList['data'] as $friend) {
                $friends[] = $friend['id'];
            }
            return $friends;
        }
        return $friendsList['data'];
    }

    /**
     * Get user friend ids
     *
     * @return array
     */
    public function getFriendIds()
    {
        return $this->getFriends(true);
    }

    /**
     * Get user friends (uid-name array) who use this application
     *
     * @param  boolean $idsOnly
     * @return array
     */
    public function getAppUserFriends($idsOnly = false)
    {
        $values = $idsOnly ? 'uid' : 'uid,name';
        $query  = 'SELECT '.$values.' FROM user WHERE uid IN(SELECT uid2 FROM friend WHERE uid1 = me()) AND is_app_user = "true"';
        $data   = $this->runFql($query);

        if (!idsOnly || empty($data)) {
            return $data;
        }

        $ids = array();
        foreach ($data as $d) {
            $ids[] = $d['uid'];
        }

        return $ids;
    }

    /**
     * Get user friend ids who use this application
     *
     * @return array
     */
    public function getAppUserFriendIds()
    {
        return $this->getAppUserFriends(true);
    }

    /**
     * Delete request and return deleted request ids
     *
     * @return array
     */
    public function getRequestIdsAfterDelete()
    {
        if (!$this->isLoggedIn()) {
            throw new SdkException('This action is only for logged in users');
        }

        if (empty($_REQUEST['request_ids'])) {
            return array();
        }

        $requestIds = explode(',', $_REQUEST['request_ids']);
        $deletedIds = array();

        foreach ($requestIds as $requestId) {
            $fullRequestId = $requestId.'_'.$this->id;
            $deleteSuccess = $this->sdk->api("/$fullRequestId", 'DELETE');
            
            if ($deleteSuccess) {
                $deletedIds[] = $fullRequestId;
            }
        }

        return $deletedIds;
    }

    /**
     * Get applicaiton access_token
     *
     * @return string|boolean
     */
    public function getApplicationAccessToken()
    {
        $params = array(
            'client_id'     => $this->config['appId'],
            'client_secret' => $this->config['appSecret'],
            'grant_type'    => 'client_credentials'
        );

        $response = $this->getFromUrl('https://graph.facebook.com/oauth/access_token', $params);

        if (!empty($response)) {
            parse_str($response, $data);
            return isset($data['access_token']) ? $data['access_token'] : false;
        }

        return false;
    }

    /**
     * Get extended (2 month) access token using existing token
     *
     * @param boolean $withExpireTime
     * @return mixed
     */
    public function getExtendedAccessToken($withExpireTime = false)
    {
        $params = array(
            'client_id'         => $this->config['appId'],
            'client_secret'     => $this->config['appSecret'],
            'grant_type'        => 'client_credentials',
            'fb_exchange_token' => $this->getAccessToken()
        );

        $response = $this->getFromUrl('https://graph.facebook.com/oauth/access_token', $params);

        if (!empty($response)) {
            parse_str($response, $data);
            return isset($data['access_token']) ? $withExpireTime ? $data : $data['access_token'] : false;
        }

        return false;
    }

    /**
     * Get data from url with cURL
     *
     * @param  string $url
     * @param  array  $params
     * @param  string $customMethod
     * @return string
     */
    public function getFromUrl($url, $params = null, $customMethod = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURL_OPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'facebook-php-3.1');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (is_array($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        if (null !== $customMethod) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customMethod);
        }

        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /**
     * Check user if tab page like or not
     * 
     * @return boolean
     */
    public function isTabPageLiked()
    {
        return isset($this->signedRequest['page']['liked']) ? $this->signedRequest['page']['liked'] : false;
    }

    /**
     * Check user if Facebook page liked before
     *
     * @param  int      $pageId
     * @return boolean
     */
    public function isPageLiked($pageId)
    {
        $like = $this->runFql('SELECT uid FROM page_fan WHERE page_id="'.$pageId.'" and uid="'.$this->id.'"');
        return $like !== false && isset($like[0]);
    }

    /**
     * Check user if admin of the page
     *
     * @return boolean
     */
    public function isTabPageAdmin()
    {
        return isset($this->signedRequest['page']['admin']) ? $this->signedRequest['page']['admin'] : false;
    }

    /**
     * Check if user give perm(s) to the application
     *
     * @param  string|array $perms
     * @return boolean
     */
    public function isPermGiven($perms)
    {
        if (is_array($perms)) {
            $perms = implode(',', $perms);
        }

        $info = $this->runFql('SELECT '.implode(',', $perms).' FROM permissions WHERE uid = me()');

        if (!empty($info)) {
            foreach ($info[0] as $v) {
                if ($v == 0) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Check if application is bookmarked
     *
     * @return boolean
     */
    public function isBookmarked()
    {
        return $this->isPermGiven('bookmarked');
    }

    /**
     * Post a message to user wall
     *
     * Params:
     * -message     : Feed Message
     * -picture     : Address of image
     * -link        : URL
     * -name        : URL Title
     * -caption     : Description under the URL Title
     * -description : Description
     * -actions     : array('name' => '', 'link' => '')
     *
     * @param  array       $params
     * @return int|boolean
     */
    public function postToWall(array $params)
    {
        $response = $this->sdk->api('/me/feed', 'POST', $params);
        return isset($response['id']) ? $response['id'] : false;
    }

    /**
     * Running FQL
     * @param  string $query
     * @return array
     */
    public function runFql($query)
    {
        $params = array(
            'method' => 'fql.query',
            'query'  => $query
        );

        return $this->sdk->api($params);
    }

    /**
     * Redirect user with javascript
     *
     * @param string $url
     */
    public function redirectWithJavascript($url)
    {
        echo '<script type="text/javascript">top.location.href = "'.$url.'";</script>';
        exit;
    }

    /**
     * Force not logged in users to login url
     *
     * @return mixed
     */
    public function forceToLogin()
    {
        if (!$this->isLoggedIn()) {
            $this->redirectWithJavascript($this->getLoginUrl());
        }
    }

    /**
     * Create an event
     *
     * Params:
     * -name        : Event Title
     * -description : Description
     * -start_time  : Start Date (unixtimestamp)
     * -end_time    : End Date (unixtimestamp)
     * -location    : Location
     * -privacy     : Privacy Info ('OPEN', 'CLOSED', 'SECRET')
     *
     * @param  array       $params
     * @return int|boolean
     */
    public function createEvent(array $eventData)
    {
        $response = $this->sdk->api('/me/events', 'POST', $eventData);
        return ($response && !empty($response) && isset($response['id'])) ? $response['id'] : false;
    }

    /**
     * Publish an open graph action
     *
     * @param  string $appNamespace Your application namespace
     * @param  string $action       Action name
     * @param  array  $objectData   The object data for your action (array('object' => 'objectUrl')). Object page source must contain open graph tags.
     * @return int|boolean
     */
    public function publishOpenGraphAction($appNamespace, $action, $objectData)
    {
        $response = $this->sdk->api("/me/{$appNamespace}:{$action}", 'POST', $objectData);
        return !empty($response['id']) ? $response['id'] : false;
    }

    /**
     * Subscribe to real-time updates
     *
     * @param  string $object
     * @param  string $fields
     * @param  string $callbackUrl
     * @param  string $verifyToken
     * @return mixed
     */
    public function subscribe($object, $fields, $callbackUrl, $verifyToken)
    {
        $params = array(
            'object'       => $object,
            'fields'       => $fields,
            'callback_url' => $callbackUrl,
            'verify_token' => $verifyToken
        );

        $response = $this->getFromUrl($this->getSubscriptionUrl(), $params);

        return ($response && $response !== 'null') ? $response : true;
    }

    /**
     * Get list of real-time updates
     *
     * @return array
     */
    public function getSubscriptions()
    {
        $response = $this->getFromUrl($this->getSubscriptionUrl());

        if (!empty($response)) {
            $data = json_decode($response, true);
            return isset($data['data']) ? $data['data'] : $data;
        }

        return false;
    }

    /**
     * Unsubscribe from real-time updates
     *
     * @param  type $object
     * @return mixed
     */
    public function unsubscribe($object = null)
    {
        $params = array();
        if (null !== $object) {
            $params = array('object' => $object);
        }

        $response = $this->getFromUrl($this->getSubscriptionUrl(), $params, 'DELETE');

        return ($response && $response != 'null') ? $response : true;
    }

    /**
     * Get subscription verification string
     *
     * @param  string $verifyToken
     * @return mixed
     */
    public function getSubscriptionChallenge($verifyToken)
    {
        if (
            $_SERVER['REQUEST_METHOD'] == 'GET' &&
            isset($_GET['hub_mode']) && $_GET['hub_mode'] == 'subscribe' &&
            isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] == $verifyToken
        ) {
            return $_GET['hub_challenge'];
        }

        return false;
    }

    /**
     * Get real-time updates posted by Facebook
     *
     * @return array
     */
    public function getSubscriptedUpdates()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            return json_decode(file_get_contents('php://input'), true);
        }

        return;
    }

    /**
     * Get real-time updates subscription url
     *
     * @return string
     */
    protected function getSubscriptionUrl()
    {
        if (null !== $this->subscriptionUrl) {
            return $this->subscriptionUrl;
        }

        $this->subscriptionUrl = "https://graph.facebook.com/{$this->config['appId']}/subscriptions?access_token=".$this->getApplicationAccessToken();
        return $this->subscriptionUrl;
    }

    /**
     * Send notification to an application user
     *
     * @param  int    $userId
     * @param  string $template
     * @param  string $href
     * @return mixed
     */
    public function sendNotification($userId, $template, $href)
    {
        $params = array(
            'access_token' => $this->getApplicationAccessToken(),
            'template'     => $template,
            'href'         => $href
        );

        $response = $this->getFromUrl('https://graph.facebook.com/'.$userId.'/notifications', $params);
        return strpos($response, 'error') !== false ? $response : true;
    }

    /**
     * Magic Call Method
     */
    public function __call($name, $arguments)
    {
        // You can continue to use SDK Callable methods
        if (method_exists($this->sdk, $name) && is_callable(array($this->sdk, $name))) {
            return call_user_func_array(array($this->sdk, $name), $arguments);
        }

        if (strpos($name, 'get') === 0) {
            $property = strtolower(substr($name, 3));

            if (method_exists($this, $property)) {
                return $this->property($arguments);
            }

            $this->setUserProfileData();

            if (isset($this->userProfile[$property])) {
                return $this->userProfile[$property];
            }

            if(isset($this->sdk->$property)) {
                return $this->sdk->$property;
            }
        }

        throw new SdkException("There is no method or property named '".$name."'");
    }
}
