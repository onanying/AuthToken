## AuthToken

在账号体系的信息系统中，对身份的鉴定是非常重要的事情，随着移动互联网时代到来，客户端的类型越来越多， 逐渐出现了一个服务器，N个客户端的格局，不同的客户端产生了不同的用户使用场景，本类根据不同的用户使用场景解决身份认证问题。

### 架构设计

[基于token的多平台身份认证架构设计](http://www.cnblogs.com/beer/p/6029861.html)

### 范例 (Example)

生成移动端access_token，存储用户信息

```php
$userdata = ['uid'=>1008,'name'=>'小花'];
$auth = new Auth();
$auth = $auth->mobileToken($userdata['uid'], $userdata); // uid建议使用类似微信的openid，而不使用数据库的原始uid
echo $auth->accessToken;
echo $auth->expires; // 默认有效期30天
```

通过access_token获取用户信息

```php
$accessToken = $_GET['access_token'];
$auth = new Auth();
$userdata = $auth->show($accessToken);
if(is_null($userdata)){
    die('access_token Authentication Failed');
}
echo $auth->uid;
echo $auth->name;
```

生成其它类型的access_token

```php
// 浏览器端Token，默认有效期2天
$auth = $auth->browserToken($userdata['uid'], $userdata);
// API应用Token，默认有效期2小时
$auth = $auth->apiToken($userdata['uid'], $userdata);
// PC端给移动端授权Token，默认有效期5分钟
$auth = $auth->pamToken($userdata['uid'], $userdata);
// 移动端给PC端授权Token，默认有效期5分钟
$auth = $auth->mapToken($userdata['uid'], $userdata);
```