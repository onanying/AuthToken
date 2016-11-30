<?php

/**
 * 基于token的多平台身份认证类
 * @author 刘健 <59208859@qq.com>
 */
class Auth
{

    private $db = '';
    private $dbConf = ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'];

    public function __construct()
    {
        $this->connectDB();
    }

    // 连接数据库
    private function connectDB()
    {
        $this->db = new Redis();
        $state = $this->db->connect($this->dbConf['host'], $this->dbConf['port']);
        if ($state == false) {
            die('redis connect failure');
        }
        if (!is_null($this->dbConf['auth'])) {
            $this->db->auth($this->dbConf['auth']);
        }
    }

    // 移动端Token
    public function mobileToken($uid, $userdata, $expires = 2592000)
    {
        $accessToken = $this->store('mobile', $uid, $userdata, $expires);
        return ['access_token' => $accessToken, 'expires' => $expires];
    }

    // 浏览器端Token
    public function browserToken($uid, $userdata, $expires = 172800)
    {
        $accessToken = $this->store('browser', $uid, $userdata, $expires);
        return ['access_token' => $accessToken, 'expires' => $expires];
    }

    // API应用Token
    public function apiToken($uid, $userdata, $expires = 7200)
    {
        $accessToken = $this->store('api', $uid, $userdata, $expires);
        return ['access_token' => $accessToken, 'expires' => $expires];
    }

    // PC端给移动端授权Token
    public function pamToken($uid, $userdata, $expires = 300)
    {
        $accessToken = $this->store('api', $uid, $userdata, $expires);
        return ['access_token' => $accessToken, 'expires' => $expires];
    }

    // 移动端给PC端授权Token
    public function mapToken($uid, $userdata, $expires = 300)
    {
        $accessToken = $this->store('api', $uid, $userdata, $expires);
        return ['access_token' => $accessToken, 'expires' => $expires];
    }

    // 展示token数据
    public function show($accessToken)
    {
        // 解码access_token
        $tokenData = self::accessTokenDecode($accessToken);
        if ($tokenData === false) {
            return null;
        }
        // 取出数据
        $key = "token:{$tokenData['tokenType']}:{$tokenData['uid']}";
        $userdata = $this->getData($key);
        if (empty($userdata)) {
            return null;
        }
        // 判断是否合法
        if ($userdata['__secret__'] != $tokenData['secret']) {
            return null;
        }
        // 剔除秘钥
        unset($userdata['__secret__']);
        // 返回
        return $userdata;
    }

    // 存储token数据
    private function store($tokenType, $uid, $userdata, $expires)
    {
        $secret = sha1($uid . time());
        // 增加secret至用户信息
        $userdata = (array) $userdata;
        $userdata['__secret__'] = $secret;
        // 保存数据
        $key = "token:{$tokenType}:{$uid}";
        $this->setData($key, $userdata, $expires);
        // 返回access_token
        return self::accessTokenEncode($tokenType, $uid, $secret);
    }

    // 保存数据
    private function setData($key, $userdata, $expires)
    {
        $this->db->hMset($key, $userdata);
        $this->db->setTimeout($key, $expires);
    }

    // 取出数据
    private function getData($uid)
    {
        return $this->db->hGetAll($uid);
    }

    // access_token编码
    private static function accessTokenEncode($tokenType, $uid, $secret)
    {
        return self::base64UrlEncode("$tokenType,$uid,$secret");
    }

    // access_token解码
    private static function accessTokenDecode($accessToken)
    {
        $accessToken = self::base64UrlDecode($accessToken);
        $ary = explode(',', $accessToken);
        if (count($ary) != 3) {
            return false;
        }
        return ['tokenType' => $ary[0], 'uid' => $ary[1], 'secret' => $ary[2]];
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
