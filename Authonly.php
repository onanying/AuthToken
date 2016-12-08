<?php

/**
 * 基于token的身份认证类 (被迫下线模式)
 * @author 刘健 <59208859@qq.com>
 */
class Authonly
{

    protected $db = ''; // 数据库对象

    protected $dbConf = ['host' => '127.0.0.1', 'port' => '6379', 'auth' => '']; // 数据库配置

    protected $accessToken = ''; // 访问令牌

    protected $savePath = ''; // redis的保存路径

    public function __construct($conf = null)
    {
        empty($conf) or $this->config($conf);
        $this->connectDB();
    }

    // 配置
    public function config($conf)
    {
        !isset($conf['dbConf']) or $this->dbConf = $conf['dbConf'];
        !isset($conf['savePath']) or $this->setSavePath($conf['savePath']);
        !isset($conf['accessToken']) or $this->setToken($conf['accessToken']);
    }

    // 连接数据库
    protected function connectDB()
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

    // 设置保存数据的路径
    public function setSavePath($path)
    {
        $this->savePath = "auth:{$path}";
    }

    // 创建Token
    public function createToken($uid, $expires)
    {
        $accessToken = $this->create($uid, $expires);
        $this->accessToken = $accessToken;
        return (object) ['accessToken' => $accessToken, 'expires' => $expires];
    }

    public function setToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    // 设置用户数据
    public function setUserData($newUserData)
    {
        $oldUserData = $this->userData();
        // 效验token是否有效
        if (!is_null($oldUserData)) {
            $tokenData = self::accessTokenDecode($this->accessToken);
            $this->store($tokenData['uid'], $newUserData);
            return true;
        }
        return false;
    }

    // 重设有效期
    public function resetExpires($expires)
    {
        $oldUserData = $this->userData();
        // 效验token是否有效
        if (!is_null($oldUserData)) {
            $tokenData = self::accessTokenDecode($this->accessToken);
            $key = "{$this->savePath}:{$tokenData['uid']}";
            $this->setDataExpires($key, $expires);
            return true;
        }
        return false;
    }

    // 返回token内存储的全部用户数据
    public function userData()
    {
        // 解码access_token
        $tokenData = self::accessTokenDecode($this->accessToken);
        if ($tokenData === false) {
            // token解码失败
            return null;
        }
        // 取出数据
        $key = "{$this->savePath}:{$tokenData['uid']}";
        $userData = $this->getData($key);
        if (empty($userData)) {
            // token不存在
            return null;
        }
        // 判断是否合法
        if ($userData['__secret__'] != $tokenData['secret']) {
            // token的秘钥验证失败
            return null;
        }
        // 剔除秘钥
        unset($userData['__secret__']);
        // 返回用户数据
        return (object) $userData;
    }

    // 创建token
    protected function create($uid, $expires)
    {
        $secret = sha1($uid . uniqid());
        // 生成secret
        $userData['__secret__'] = $secret;
        // 保存数据
        $key = "{$this->savePath}:{$uid}";
        $this->setData($key, $userData);
        $this->setDataExpires($key, $expires);
        // 返回access_token
        return self::accessTokenEncode($uid, $secret);
    }

    // 保存用户数据
    protected function store($uid, $userData)
    {
        $key = "{$this->savePath}:{$uid}";
        $userData = (array) $userData;
        // 判断有效期
        if ($this->getDataExpires($key) > 0) {
            // 保存数据
            $this->setData($key, $userData);
        }
    }

    // 保存数据
    protected function setData($key, $userData)
    {
        $this->db->hMset($key, $userData);
    }

    // 设置有效期
    protected function setDataExpires($key, $expires)
    {
        $this->db->setTimeout($key, $expires);
    }

    // 取出数据
    protected function getData($key)
    {
        return $this->db->hGetAll($key);
    }

    // 获取数据的有效期
    protected function getDataExpires($key)
    {
        return $this->db->ttl($key);
    }

    // access_token编码
    protected static function accessTokenEncode($uid, $secret)
    {
        return self::base64UrlEncode("$uid,$secret");
    }

    // access_token解码
    protected static function accessTokenDecode($accessToken)
    {
        $accessToken = self::base64UrlDecode($accessToken);
        $ary = explode(',', $accessToken);
        if (count($ary) != 2) {
            return false;
        }
        return ['uid' => $ary[0], 'secret' => $ary[1]];
    }

    // base64url编码
    protected static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // base64url解码
    protected static function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

}
