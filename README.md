yii2 wechat
===============================

yii2 wechat， yii2微信扩展。最适合yii2项目的微信sdk，不依赖任何其他微信sdk。


安装
---------------
1. 使用composer
     composer的安装以及国内镜像设置请点击[此处](http://www.phpcomposer.com/)
     
     ```bash
     $ cd /path/to/yii2-app
     $ composer require "feehi/yii2-wechat"
     $ composer install -vvv
     ```
 

配置
-------------
yii2 wechat是作为一个组件提供服务的，所以得配置yii2 wechat组件。打开common/config/main.php在components块内增加

```bash
    'components' => [
        'wechat' => [
            'class' => feehi\wechat\Wechat::className(),
            'appId' => 'xxx',
            'appSecret' => 'yyy',
            'token' => 'zzz',
            'encodingAesKey' => 'bbb',
            'redirectUri' => 'site/login'//回调路由
        ],
    ]
```


核心API
-------------
```php
    /** @var $wechat \feehi\wechat\Wechat */
    $wechat = yii::$app->get('wechat');
    
    $wechat->checkSignature(); //检验echoStr
    $wechat->getServerAccessToken(); //获取服务端access_token
    $wechat->createMenu(); //创建自定义菜单
    $wechat->getLoginUrl(); //获取微信登陆授权url
    $wechat->getAccessToken(); //获取用户access_token
    $wechat->getImplicitAccessToken(); //获取隐士授权用户的access_token
    $wechat->getUserInfo(); //根据用户access_token获取用户信息
    $wechat->createTempMedia(); //创建临时媒体素材
    $wechat->recieveWechatPush(); //接受微信推送的消息和事件
    $wechat->assembleWechatResponse(); //组装需要返回给微信的xml
```

P.S 以上基本上都是返回stdClass object，可以在ide中增加注释/** @var $wechat \feehi\wechat\Wechat */来跳转sdk源码。