<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2018-01-09 19:04
 */

namespace feehi\wechat;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use yii\base\Exception;
use feehi\wechat\support\XML;

class Helper
{
    /**
     * 数组转xml字符串
     *
     * @param $arr
     * @return string
     */
    public static function arrayToXml($arr)
    {
        return XML::build($arr);
    }

    /**
     * http request
     *
     * @param $url
     * @param string $method
     * @param array $options
     * @return \stdClass
     * @throws Exception
     */
    public static function httpRequest($url, $method='get', $options=[])
    {
        static $client = null;
        if( $client == null ) $client = new Client();
        $response = $client->request($method, $url, $options);
        if( ! $response instanceof Response) throw new Exception("Request $url failed.");
        $result = json_decode( $response->getBody()->getContents() );
        //if( !isset( $result->errcode ) && ( !isset($result->access_token) || !isset($result->openid) ) ) throw new Exception("Request $url response with no errcode.");
        if (isset($result->errcode) && $result->errcode != 0) {
            throw new Exception("At request url {$url} Wechat server returned: {$response->getBody()}");
        }
        return $result;
    }
}
