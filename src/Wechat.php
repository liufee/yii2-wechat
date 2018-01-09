<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2018-01-04 12:24
 */
namespace feehi\wechat;

use yii;
use yii\base\Exception;
use yii\helpers\Url;

class Wechat extends \yii\base\BaseObject
{

    public $baseUri = "https://open.weixin.qq.com/";

    public $baseApiUri = "https://api.weixin.qq.com/cgi-bin/";

    public $appId;

    public $appSecret;

    public $token;

    public $encodingAesKey;

    public $redirectUri;

    public function init()
    {
        parent::init();
        if(!$this->appId) throw new Exception("Wechat component appId must be set");
        if(!$this->appSecret) throw new Exception("Wechat component appSecret must be set");
        if(!$this->token) throw new Exception("Wechat component token must be set");
    }

    /**
     * 验证signature判断是否输出echoStr
     *
     * @param $get array $_GET超全局数组
     * @return bool
     */
    public function checkSignature($get)
    {
        $signature = $get['signature'];
        $timestamp = $get["timestamp"];
        $nonce = $get["nonce"];

        $tmpArr = array($this->token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        return $tmpStr == $signature;
    }

    /**
     * 获取微信服务端access_token
     *
     * @return string
     */
    public function getServerAccessToken()
    {
        $cache = yii::$app->getCache();
        if( ($accessToken = $cache->get('wechat_server_access_token')) === false ){
            $result = Helper::HttpRequest($this->baseApiUri . 'token', 'get', [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid' => $this->appId,
                    'secret' => $this->appSecret,
                ]
            ]);
            $cache->set('wechat_server_access_token', $result->access_token, $result->expires_in);
            $accessToken = $result->access_token;
        }
        return $accessToken;
    }

    /**
     * 创建自定义菜单
     *
     *
     * @param array $data
     * [
     *    'button' => [
     *          ['type'=>'view', 'name'=>'飞嗨', 'url'=>'http://www.feehi.com'],
     *          ['type'=>'view', 'name'=>'博客', 'url'=>'https://blog.feehi.com'],
     *          ['type'=>'view', 'name'=>'关于', 'url'=>'http://www.feehi.com/page/about'],
     *     ]
     *  ]
     */
    public function createMenu(array $data)
    {
        Helper::HttpRequest($this->baseApiUri . 'menu/create', 'post', [
            'query' => [
                'access_token' => $this->getServerAccessToken(),
            ],
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * 获取登陆url
     *
     * @param string $redirectUri 回调地址 如果redirectUri为空则使用组件配置的回调地址
     * @param string $state 微信回调原样返回参数
     * @param string $scope 仅能为snsapi_userinfo或snsapi_base,具体区别请查阅微信文档
     * @return string
     * @throws \yii\base\Exception
     */
    public function getLoginUrl($redirectUri='', $scope='snsapi_userinfo', $state='')
    {
        if( !in_array($scope, ['snsapi_userinfo', 'snsapi_base']) ) throw new Exception("scope must be snsapi_userinfo or snsapi_base");
        $params = [
            'appid' => $this->appId,
            'redirect_uri' => $redirectUri ? urlencode( $redirectUri ) : Url::to([$this->redirectUri], true),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ];
        $queryString = http_build_query($params);
        return $this->baseUri . "connect/oauth2/authorize?" . $queryString . '#wechat_redirect';
    }

    /**
     * 获取网页授权token
     *
     * @param $code string 上一步返回的code
     * @return \stdClass
     */
    public function getAccessToken($code)
    {
        $queryString = http_build_query([
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ]);
        $result = Helper::httpRequest(str_replace('/cgi-bin', '', $this->baseApiUri) . 'sns/oauth2/access_token?' . $queryString);
        return $result;
    }

    /**
     * 隐示授权登陆获取用户信息
     *
     * @param $code
     * @return mixed
     */
    public function getImplicitAccessToken($code)
    {
        $queryString = http_build_query([
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
        $result = Helper::httpRequest($this->baseUri . 'sns/oauth2/access_token?' . $queryString);
        return $result;
    }

    /**
     * 获取用户信息 授权类型必须scope为 snsapi_userinfo
     *
     * @param $accessToken
     * @param $openid
     * @param string $lang
     * @return mixed
     */
    public function getUserInfo($accessToken, $openid, $lang = 'zh_CN')
    {
        $queryString = http_build_query([
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => $lang,
        ]);
        $result = Helper::httpRequest(str_replace('/cgi-bin', '', $this->baseApiUri) . 'sns/userinfo?' . $queryString);
        return $result;
    }

    /**
     * 创建临时素材
     *
     * @param $file
     * @return \stdClass
     * @throws \yii\base\Exception
     */
    public function createTempMedia($file)
    {
        if( is_resource($file) ){
            $resource = $file;
        }else{
            if( is_file($file) ){
                $resource = fopen($file, 'r');
            }else{
                throw new Exception("file must be resource or a exists full file path");
            }
        }
         return Helper::httpRequest($this->baseApiUri . "media/upload?access_token=" . $this->getServerAccessToken() . "&type=image", 'post', [
             'multipart' => [
                 [
                     'name' => 'media',
                     'contents' => $resource,
                 ],
             ]
        ]);
    }

    /**
     * 接收微信push的消息及事件
     *
     * @param $get
     * @return array
     * @throws \Exception
     */
    public function recieveWechatPush($get)
    {
        $post = file_get_contents("php://input");
        if( isset( $get['encrypt_type'] ) && $get['encrypt_type'] == 'aes' ){//加密或者兼容模式
            if( strpos($post, '<ToUserName>') ){//兼容模式
                $post = preg_replace('/<FromUserName><(.*)<\/MsgId>/isu', '', $post);
            }
            error_reporting(0);
            include_once "msgcrypt/wxBizMsgCrypt.php";
            error_reporting(2048);
            if( !$this->encodingAesKey ) throw new Exception("Encryption or compatible pattern must configure wechat component encodingAesKey");
            $pc = new \WXBizMsgCrypt($this->token, $this->encodingAesKey, $this->appId);
            $msgSignature = $get['msg_signature'];
            $timestamp = $get['timestamp'];
            $nonce = $get['nonce'];
            $msg = '';
            $errCode = $pc->decryptMsg($msgSignature, $timestamp, $nonce, $post, $msg);
            if ($errCode != 0) throw new Exception("Decode wechat message error: " . $errCode);
        }else{
            $msg = $post;
        }
        $xml = simplexml_load_string($msg);
        $msgType = (string)$xml->MsgType;
        if( $msgType == 'event' ){//事件
            $data = $this->parsePushEvent($xml);
        }else {//消息
            $data = $this->parsePushMessage($xml, $msgType);
        }
        return $data;
    }

    private function parsePushEvent($xml)
    {
        $data = [
            'ToUserName' => (string)$xml->ToUserName,
            'FromUserName' => (string)$xml->FromUserName,
            'CreateTime' => (string)$xml->CreateTime,
            'MsgType' => 'event',
            'Event' => (string)$xml->Event,
        ];
        switch ($data['Event']){
            case "subscribe":
            case "unsubscribe":
            case "SCAN":
                $data["EventKey"] = (string)$xml->EventKey;
                $data["Ticket"] = (string)$xml->Ticket;
                break;
            case "LOCATION":
                $data["Latitude"] = (string)$xml->Latitude;
                $data["Longitude"] = (string)$xml->Longitude;
                $data["Precision"] = (string)$xml->Precision;
                break;
            case "CLICK":
                $data["EventKey"] = (string)$xml->EventKey;
                break;
        }
        return $data;
    }

    private function parsePushMessage($xml, $msgType)
    {
        $data = [
            'ToUserName' => (string)$xml->ToUserName,
            'FromUserName' => (string)$xml->FromUserName,
            'CreateTime' => (string)$xml->CreateTime,
            'MsgType' => $msgType,
            'MsgId' => (string)$xml->MsgId,
        ];
        switch ($msgType) {
            case "text":
                $data["Content"] = (string)$xml->Content;
                break;
            case "image":
                $data["PicUrl"] = (string)$xml->PicUrl;
                $data["MediaId"] = (string)$xml->MediaId;
                break;
            case "voice":
                $data["MediaId"] = (string)$xml->MediaId;
                $data["Format"] = (string)$xml->Format;
                if (isset($xml->Recognition)) $data["Recognition"] = (string)$xml->Recognition;
                break;
            case "video":
            case "shortvideo":
                $data["MediaId"] = (string)$xml->MediaId;
                $data["ThumbMediaId"] = (string)$xml->ThumbMediaId;
                break;
            case "location":
                $data["Location_X"] = (string)$xml->Location_X;
                $data["Location_Y"] = (string)$xml->Location_Y;
                $data["Scale"] = (string)$xml->Scale;
                $data["Label"] = (string)$xml->Label;
                break;
            case "link":
                $data["Title"] = (string)$xml->Title;
                $data["Description"] = (string)$xml->Description;
                $data["Url"] = (string)$xml->Url;
                break;
        }
        return $data;
    }

    /**
     * 组装返回给用户xml
     *
     * @param $type
     * @param $to
     * @param $from
     * @param $options
     * @return string
     * @throws \Exception
     */
    public function assembleWechatResponse($type, $to, $from, $options)
    {
        $timestamp = time();
        $data = [
            'ToUserName' => $to,
            'FromUserName' => $from,
            'CreateTime' => $timestamp,
            'MsgType' => $type,
        ];
        switch ($type){
            case "text":
                if( !isset($options['Content']) ) throw new Exception("text type must contains Content key ");
                $data['Content'] = $options['Content'];
                break;
            case "image":
                if( !isset($options['MediaId']) ) throw new Exception("{$type} type must contains MediaId key ");
                $data['Image']['MediaId'] = $options['MediaId'];
                break;
            case "voice":
                if( !isset($options['MediaId']) ) throw new Exception("{$type} type must contains MediaId key ");
                $data['voice']['MediaId'] = $options['MediaId'];
                break;
            case "video":
                if( !isset($options['MediaId']) ) throw new Exception("video type must contains MediaId key ");
                $data['Video']['MediaId'] = $options['MediaId'];
                isset($options['Title']) && $data['Video']['Title'] = $options['Title'];
                isset($options['Description']) && $data['Video']['Description'] = $options['Description'];
                break;
            case "music":
                if( !isset($options['ThumbMediaId']) ) throw new Exception("music type must contains ThumbMediaId key ");
                $data['ThumbMediaId'] = $options['ThumbMediaId'];
                isset($options['Title']) && $data['Music']['Title'] = $options['Title'];
                isset($options['Description']) && $data['Music']['Description'] = $options['Description'];
                isset($options['MusicURL']) && $data['Music']['MusicURL'] = $options['MusicURL'];
                isset($options['HQMusicUrl']) && $data['Music']['HQMusicUrl'] = $options['HQMusicUrl'];
                break;
            case "news":
                $data['ArticleCount'] = count($options['articles']);
                $data['Articles'] = $options['articles'];
                break;
            default:
                throw new Exception("Type only can be text,image,voice,video,music,news");
        }
        $text = Helper::arrayToXml($data);
        if( $this->encodingAesKey ) {
            include_once "msgcrypt/wxBizMsgCrypt.php";
            $pc = new \WXBizMsgCrypt($this->token, $this->encodingAesKey, $this->appId);
            $nonce = rand(1, 99999);
            $encryptMsg = '';
            $errCode = $pc->encryptMsg($text, $timestamp, $nonce, $encryptMsg);
            if ($errCode != 0) throw new \Exception("Crypt message error $errCode");
        }else{
            $encryptMsg = $text;
        }
        return $encryptMsg;
    }
}
