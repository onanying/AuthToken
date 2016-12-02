## AuthToken

基于token的一套账户体系下多个产品/多种平台/多种运行模式的身份认证类

### 架构设计

[基于token的多平台身份认证架构设计](http://www.cnblogs.com/beer/p/6029861.html)

### 功能简介

- 支持一个账号体系下多个产品
- 每个产品都支持4种终端类型的token
- 有3种类型的token支持两种运行模式
- 支持 踢出其他相同终端 与 可多个相同终端同时在线 两种运行模式

### 说明文档

#### 存储用户信息，并生成移动端access_token

productName：你的产品名称  
uid: 用户id  
userData: 需要存储的用户数据  
runMode: 有 Auth::CONSTANT 、Auth::VARIATIONAL 两个参数 两种运行模式，后面会详细说明

范例代码

```php
$userData = ['uid' => 1008, 'name' => '小花'];
$auth = new Auth();
$params = ['productName' => 'product1', 'uid' => $userData['uid'], 'userData' => $userData, 'runMode' => Auth::CONSTANT];
$auth = $auth->mobileToken($params); // uid建议使用类似微信的openid，而不使用数据库的原始uid
var_dump($auth);
```

返回结果

	object(stdClass)#3 (2) {
	  ["accessToken"]=>
	  string(82) "cHJvZHVjdDEsbW9iaWxlLDEwMDgsMmM5NTUyYzBlOGJmZGQ4MDlhZjI1ZmU5MzQ3MWQ5NDJhODk4YWFkOQ"
	  ["expires"]=>
	  int(600668)
	}

#### access_token 的4种终端类型

**1. 移动端Token**

用于手机app

```php
// 移动端Token，默认有效期7天
$params = ['productName' => 'product1', 'uid' => $userData['uid'], 'userData' => $userData, 'runMode' => Auth::VARIATIONAL];
$auth = $auth->mobileToken($params); 
```

**2. 浏览器端Token**

用于PC或移动端的H5应用

```php
// 浏览器端Token，默认有效期2天
$params = ['productName' => 'product1', 'uid' => $userData['uid'], 'userData' => $userData, 'runMode' => Auth::CONSTANT];
$auth = $auth->browserToken($params); 
```

**3. API应用Token**

用于PC端应用软件

```php
// API应用Token，默认有效期2小时
$params = ['productName' => 'product1', 'uid' => $userData['uid'], 'userData' => $userData, 'runMode' => Auth::CONSTANT];
$auth = $auth->apiToken($params); 
```

**4. 登陆授权Token**

用于某个端通过保存在本地的uid,password或第三方平台返回的openid/unionid获取用户信息，然后通过用户信息生成一个token，再通过url或二维码的形式传递给另一个端，另一端接受后取出用户信息完成登录动作，等类似场景

```php
// 登陆授权Token，默认有效期5分钟，注意不需传runMode参数
$params = ['productName' => 'product1', 'uid' => $userData['uid'], 'userData' => $userData];
$auth = $auth->loginToken($params);
```

#### 通过access_token获取用户数据

当access_token效验成功时返回用户数据，出现错误时会返回两种状态：   
- token不存在：表示token可能超时了，重新获取即可   
- token身份验证失败：表明该token为非法请求，或该用户在其他相同终端有登录

范例代码

```php
$accessToken = $_GET['access_token'];
$auth = new Auth();
$result = $auth->show($accessToken);
if ($result->errorCode == 1) {
    die('access_token not exist');
}
if ($result->errorCode == 2) {
    die('access_token auth failed');
}
var_dump($result->userData);
```

返回结果

	object(stdClass)#3 (2) {
	  ["uid"]=>
	  string(4) "1008"
	  ["name"]=>
	  string(6) "小花"
	}

#### 踢出 与 可同时登录 两种运行模式

runMode: 有 Auth::CONSTANT 、Auth::VARIATIONAL 两个参数
Auth::VARIATIONAL 为 踢出模式  
Auth::CONSTANT 为 可同时登录模式

**1. 踢出模式**

踢出模式每获取一次token，会生成一个新的access_token，有效期也重新开始，且旧的access_token失效。

> 像QQ一样，同一账号在另一个手机登录时，本手机会提示该账号已经在其他设备登录，但是登录PC端却不会，因为不是同一类型终端。

如何实现呢？

> 因为access_token本身是会过期的，当 $auth->show($accessToken) 返回errorCode等于1时，说明access_token只是过期了，没有其他人登录，通知app重新获取新的access_token即可；当返回errorCode等于2时，说明token被其他用户生成了新的token，也就是说该账号在其他同类型终端登录了，现在要通知app把该用户退出至登陆页，并做提示。

**2. 可同时登录模式**

可同时登录模式每获取一次token，当该用户的token还未过期时，不会生成新的token，有效期也不会重新开始，只有在token过期后才会生成新的token。

> 像QQ邮箱一样，同一账号可在多个电脑上登录，并不会把其他电脑上的账号强制退出。

如何实现呢？

> 因为要支持多个相同终端同时登录，所以 $auth->show($accessToken) 返回errorCode等于1或2时，都代表了该token失效了，通知app重新获取一次token即可，因为有效期内token不会变，最终多个相同终端的token会变成同一个token，当然也都一样同时过期。