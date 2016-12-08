## AuthToken

基于token的一个账户体系下多种运行模式的身份认证

### 架构设计

[基于token的多平台身份认证架构设计](http://www.cnblogs.com/beer/p/6029861.html)

### 功能简介

- [Authmulti.php 同时在线模式](https://github.com/onanying/AuthToken#authmultiphp-同时在线模式)
- [Authonly.php 被迫下线模式](https://github.com/onanying/AuthToken#authonlyphp-被迫下线模式)



## Authmulti.php 同时在线模式

> 像QQ邮箱一样，同一账号可在多个电脑上登录，并不会把其他电脑上的账号强制退出

#### 创建一个新token并存入用户数据

```php
// 实例化
$auth = new Authmulti(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-multi']);
// 创建一个新的token
$expires = 7200;
$token = $auth->createToken($expires);
var_dump($token);
// 在这个token里存入用户数据
$auth->setUserData(['uid' => 1008, 'name' => '小花']);
$userData = $auth->userData();
var_dump($userData);
```

返回结果

	object(stdClass)#3 (2) {
	  ["accessToken"]=>
	  string(54) "M2Q2MTU0NWQwNTdjNGIzYTYwNjA5ZDQ0ZDIzYmUzMGNmY2ViMTk2MA"
	  ["expires"]=>
	  int(7200)
	}
	object(stdClass)#4 (2) {
	  ["uid"]=>
	  string(4) "1008"
	  ["name"]=>
	  string(6) "小花"
	}

#### 修改某个token里的用户数据

```php
// 实例化
$auth = new Authmulti(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-multi']);
// 设置要操作的token
$auth->setToken('M2Q2MTU0NWQwNTdjNGIzYTYwNjA5ZDQ0ZDIzYmUzMGNmY2ViMTk2MA');
// 在这个token里存入新的用户数据
$status = $auth->setUserData(['uid' => 1008, 'name' => '小美']);
var_dump($status);
```

成功时返回结果

	bool(true)

失败时返回结果 (说明token无效或savePath配置错误)

	bool(false)

#### 获取某个token中存储的用户数据

```php
// 实例化
$auth = new Authmulti(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-multi']);
// 设置要操作的token
$auth->setToken('M2Q2MTU0NWQwNTdjNGIzYTYwNjA5ZDQ0ZDIzYmUzMGNmY2ViMTk2MA');
// 获取这个token里的用户数据
$userData = $auth->userData();
var_dump($userData);
```

成功时返回结果

	object(stdClass)#4 (2) {
	  ["uid"]=>
	  string(4) "1008"
	  ["name"]=>
	  string(6) "小美"
	}

失败时返回结果 (说明token无效或savePath配置错误)

	NULL

#### 重置一个token的有效期

```php
// 实例化
$auth = new Authmulti(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-multi']);
// 设置要操作的token
$auth->setToken('M2Q2MTU0NWQwNTdjNGIzYTYwNjA5ZDQ0ZDIzYmUzMGNmY2ViMTk2MA');
// 重置有效期
$status = $auth->resetExpires(88888);
var_dump($status);
```

成功时返回结果

	bool(true)

失败时返回结果 (说明token无效或savePath配置错误)

	bool(false)



## Authonly.php 被迫下线模式

> 像QQ一样，同一账号在另一个手机登录时，本手机会提示该账号已经在其他设备登录

#### 创建一个新token并存入用户数据

```php
// 实例化
$auth = new Authonly(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-only']);
// 创建一个新的token
$uid = 10008;
$expires = 7200;
$token = $auth->createToken($uid, $expires);
var_dump($token);
// 在这个token里存入用户数据
$auth->setUserData(['uid' => 1008, 'name' => '小花']);
$userData = $auth->userData();
var_dump($userData);
```

返回结果

	object(stdClass)#3 (2) {
	  ["accessToken"]=>
	  string(62) "MTAwMDgsZWVkZDYxNmUwMWUxZjljNWM2MzkxZjc5MjE0NTEwYjg3NTA1ODMwNg"
	  ["expires"]=>
	  int(7200)
	}
	object(stdClass)#4 (2) {
	  ["uid"]=>
	  string(4) "1008"
	  ["name"]=>
	  string(6) "小花"
	}

#### 修改某个token里的用户数据

```php
// 实例化
$auth = new Authonly(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-only']);
// 设置要操作的token
$auth->setToken('MTAwMDgsZWVkZDYxNmUwMWUxZjljNWM2MzkxZjc5MjE0NTEwYjg3NTA1ODMwNg');
// 在这个token里存入新的用户数据
$status = $auth->setUserData(['uid' => 1008, 'name' => '小美']);
var_dump($status);
```

成功时返回结果

	bool(true)

失败时返回结果 (说明token无效或savePath配置错误)

	bool(false)


#### 获取某个token中存储的用户数据

```php
// 实例化
$auth = new Authonly(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-only']);
// 设置要操作的token
$auth->setToken('MTAwMDgsZWVkZDYxNmUwMWUxZjljNWM2MzkxZjc5MjE0NTEwYjg3NTA1ODMwNg');
// 获取这个token里的用户数据
$userData = $auth->userData();
var_dump($userData);
```

成功时返回结果

	object(stdClass)#4 (2) {
	  ["uid"]=>
	  string(4) "1008"
	  ["name"]=>
	  string(6) "小美"
	}

失败时返回结果 (说明token无效或savePath配置错误)

	NULL

#### 重置一个token的有效期

```php
// 实例化
$auth = new Authonly(['dbConf' => ['host' => '192.168.0.68', 'port' => '6379', 'auth' => '123456'], 'savePath' => 'test-only']);
// 设置要操作的token
$auth->setToken('MTAwMDgsZWVkZDYxNmUwMWUxZjljNWM2MzkxZjc5MjE0NTEwYjg3NTA1ODMwNg');
// 重置有效期
$status = $auth->resetExpires(88888);
var_dump($status);
```

成功时返回结果

	bool(true)

失败时返回结果 (说明token无效或savePath配置错误)

	bool(false)
