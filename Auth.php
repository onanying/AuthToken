<?php

/**
 * 基于token的一套账户体系下多个产品/多种平台/多种运行模式的身份认证类
 * @author 刘健 <59208859@qq.com>
 */
class Auth
{

    private $db = ''; // 数据库对象

    private $dbConf = ['host' => '127.0.0.1', 'port' => '6379', 'auth' => '']; // 数据库配置

    const CONSTANT = 0; // 不变Token模式(有效期内)，同一个账号支持多个相同设备同时访问

    const VARIATIONAL = 1; // 变化Token模式(有效期内)，同一个账号在多个相同设备只允许最后登录的设备访问

    public function __construct($conf = null)
    {
        empty($conf) or $this->configDB($conf);
        $this->connectDB();
    }

    // 配置数据库
    public function configDB($conf)
    {
        $this->dbConf = $conf;
    }

    // 连接数据库
    private function connectDB()
    {
        $this->db = new Redis();
        $state = $this->db->connect($this->dbConf['host'], $this->dbConf['port']);
        if ($state == false) {
            die('redis connect failure');
        }
        if (!empty($this->dbConf['auth'])) {
            $this->db->auth($this->dbConf['auth']);
        }
    }

    // 移动端Token
    public function mobileToken($params)
    {
        return $this->switchToken('mobile', 604800, $params);
    }

    // 浏览器端Token
    public function browserToken($params)
    {
        return $this->switchToken('browser', 172800, $params);
    }

    // API应用Token
    public function apiToken($params)
    {
        return $this->switchToken('api', 7200, $params);
    }

    // 登陆授权Token
    public function loginToken($params)
    {
        $params['runMode'] = self::VARIATIONAL; // 强制使用变化Token模式
        return $this->switchToken('login', 300, $params);
    }

    // 展示token数据
    public function show($accessToken)
    {
        // 解码access_token
        $tokenData = self::accessTokenDecode($accessToken);
        if ($tokenData === false) {
            // token解码失败
            return (object) ['errorCode' => 2];
        }
        // 取出数据
        $key = "auth:{$tokenData['productName']}:{$tokenData['tokenType']}:{$tokenData['uid']}";
        $userData = $this->getData($key);
        if (empty($userData)) {
            // token不存在
            return (object) ['errorCode' => 1];
        }
        // 判断是否合法
        if ($userData['__secret__'] != $tokenData['secret']) {
            // token验证失败
            return (object) ['errorCode' => 2];
        }
        // 剔除秘钥
        unset($userData['__secret__']);
        // 返回
        return (object) ['errorCode' => 0, 'userData' => (object) $userData];
    }

    // 切换token类型
    private function switchToken($tokenType, $defaultExpires, $params)
    {
        if (!isset($params['runMode']) || !isset($params['productName']) || !isset($params['uid']) || !isset($params['userData'])) {
            die('not found params keys [productName,uid,userData,runMode]');
        }
        $params['expires'] = isset($params['expires']) ? $params['expires'] : $defaultExpires;
        switch ($params['runMode']) {
            case self::CONSTANT:
                return $this->constantToken($params['productName'], $tokenType, $params['uid'], $params['userData'], $params['expires']);
                break;
            case self::VARIATIONAL:
                return $this->variationalToken($params['productName'], $tokenType, $params['uid'], $params['userData'], $params['expires']);
                break;
        }
    }

    // 有效期内不变的Token
    private function constantToken($productName, $tokenType, $uid, $userData, $expires)
    {
        $safetyTime = 1;
        $expires += $safetyTime;
        $key = "auth:{$productName}:{$tokenType}:{$uid}";
        $cacheExpires = $this->getDataExpires($key);
        if ($cacheExpires >= $safetyTime) {
            // 只修改用户数据
            $this->edit($productName, $tokenType, $uid, $userData);
            $cacheUserdata = $this->getData($key);
            $accessToken = self::accessTokenEncode($productName, $tokenType, $uid, $cacheUserdata['__secret__']);
            return (object) ['accessToken' => $accessToken, 'expires' => $cacheExpires - $safetyTime];
        } else {
            // 修改全部数据
            $accessToken = $this->store($productName, $tokenType, $uid, $userData, $expires);
            return (object) ['accessToken' => $accessToken, 'expires' => $expires - $safetyTime];
        }
    }

    // 有效期内会变化的Token
    private function variationalToken($productName, $tokenType, $uid, $userData, $expires)
    {
        $accessToken = $this->store($productName, $tokenType, $uid, $userData, $expires);
        return (object) ['accessToken' => $accessToken, 'expires' => $expires];
    }

    // 存储token数据
    private function store($productName, $tokenType, $uid, $userData, $expires)
    {
        $secret = sha1($uid . time());
        // 增加secret至用户信息
        $userData = (array) $userData;
        $userData['__secret__'] = $secret;
        // 保存数据
        $key = "auth:{$productName}:{$tokenType}:{$uid}";
        $this->setData($key, $userData, $expires);
        // 返回access_token
        return self::accessTokenEncode($productName, $tokenType, $uid, $secret);
    }

    // 修改token数据
    private function edit($productName, $tokenType, $uid, $userData)
    {
        $userData = (array) $userData;
        // 保存数据
        $key = "auth:{$productName}:{$tokenType}:{$uid}";
        $this->setData($key, $userData, null);
    }

    // 保存数据
    private function setData($key, $userData, $expires)
    {
        $this->db->hMset($key, $userData);
        is_null($expires) or $this->db->setTimeout($key, $expires);
    }

    // 取出数据
    private function getData($key)
    {
        return $this->db->hGetAll($key);
    }

    // 获取数据的有效期
    private function getDataExpires($key)
    {
        return $this->db->ttl($key);
    }

    // access_token编码
    private static function accessTokenEncode($productName, $tokenType, $uid, $secret)
    {
        return self::base64UrlEncode("$productName,$tokenType,$uid,$secret");
    }

    // access_token解码
    private static function accessTokenDecode($accessToken)
    {
        $accessToken = self::base64UrlDecode($accessToken);
        $ary = explode(',', $accessToken);
        if (count($ary) != 4) {
            return false;
        }
        return ['productName' => $ary[0], 'tokenType' => $ary[1], 'uid' => $ary[2], 'secret' => $ary[3]];
    }

    // base64url编码
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // base64url解码
    private static function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

}
