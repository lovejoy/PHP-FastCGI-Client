<?php
/*
 * A PHP Version FASTCGI CLient
 * Ref: http://www.php-internals.com/book/?p=chapt02/02-02-03-fastcgi
 */
class FastCGIClient
{
    const __FCGI_VERSION = 1;

    //FASTCGI应用的角色类型 见Ref中typedef enum _fcgi_role定义
    const __FCGI_ROLE_RESPONDER = 1;
    const __FCGI_ROLE_AUTHORIZER = 2;
    const __FCGI_ROLE_FILTER = 3;

    const __FCGI_TYPE_BEGIN = 1;
    const __FCGI_TYPE_ABORT = 2;
    const __FCGI_TYPE_END = 3;
    const __FCGI_TYPE_PARAMS = 4;
    const __FCGI_TYPE_STDIN = 5;
    const __FCGI_TYPE_STDOUT = 6;
    const __FCGI_TYPE_STDERR = 7;
    const __FCGI_TYPE_DATA = 8;
    const __FCGI_TYPE_GETVALUES = 9;
    const __FCGI_TYPE_GETVALUES_RESULT = 10;

    private static $instance;
    private static $socket;

    private $props = [];
    //单例模式实现
    private function __construct($host,$port) {
        self::$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(!is_resource(self::$socket)) {
            die("Unable to create socket");
        }
        if(!@socket_connect(self::$socket, $host, $port)) {
            die("Unable to connect to".$host.":".$port);
        }
        echo "connect success";

    }
    public static function getInstance($host, $port) {
        if( empty( self::$instance)) {
            self::$instance = new self($host,$port);
        }
        return self::$instance;

    }
    public function setProperty($k , $v) {
       $this->props[$k]  = $v;
    }
    public function getProperty($k) {
        return $this->props[$k];
    }
    /**
     * 根据fastcgi的header要求拼装消息体
     * @param $type 为 __FCGI_TYPE_* 中的一个值
     * @param $content 为fastcgi request type 的一种情况,也有自己的格式
     * @param $requestId 随便生成一个唯一的就好
     * @return
     */
    private function encodeFastCGIRecord($type, $content, $requestId) {
        $length = strlen($content);
        return chr(self::__FCGI_VERSION)
            . chr($type)
            . chr(($requestId >> 8) & 0xFF)
            . chr($requestId & 0xFF)  //这里的是协议的反推,协议是通过把这个字段按第一个字段<<8 + 第二个字段当成一个总的字段的
            . chr(($length >> 8) & 0xFF)
            . chr($length & 0xFF)
            . chr(0)
            . chr(0)
            . $content;

    }
    private function encodeNameValueParams($name, $value) {
        $nLen = strlen($name);
        $vLen = strlen($value);
        $nvPair = chr($nLen) . chr($vLen) . $name . $value; //这里先简单实现下，没考虑超过的情况
        return $nvPair;
    }
    public function request($params, $content) {
        //requestId 大小只能在1~65535
        $requestId = mt_rand(1, (1 << 16) - 1);
        //begin->params->stdin
        //begin
        $request = $this->encodeFastCGIRecord(self::__FCGI_TYPE_BEGIN,
            chr(0) //roleB1
            . chr(self::__FCGI_ROLE_RESPONDER) //roleB0
            . chr(0) //表示keepalive功能的,先简写
            . str_repeat(chr(0), 5) //reserved[5];
            , $requestId);
        //param
        $paramsRequest = '';
        foreach ($params as $key => $value) {
            $paramsRequest .= $this->encodeNameValueParams($key, $value);
        }
        $request .= $this->encodeFastCGIRecord(self::__FCGI_TYPE_PARAMS, $paramsRequest, $requestId);
        //stdin
        $request .= $this->encodeFastCGIRecord(self::__FCGI_TYPE_STDIN, $content, $requestId);
        socket_write(self::$socket,$request,strlen($request));
        $out = socket_read(self::$socket,8192);
        var_dump($out);
        socket_close(self::$socket);

    }
}
$client = FastCGIClient::getInstance("10.0.83.25","8100" );
$client->request(array(
    'GATEWAY_INTERFACE' => 'FastCGI/1.0',
    'REQUEST_METHOD'    => 'GET',
    'SCRIPT_FILENAME'   => "/home/work/lamp/webroot/1.php",
    'SCRIPT_NAME'       => "1.php",
    'QUERY_STRING'      => "a=b",
    'REQUEST_URI'       => "/1.php",
    'SERVER_SOFTWARE'   => 'fcgi_client',
    'REMOTE_ADDR'       => '127.0.0.1',
    'REMOTE_PORT'       => '9985',
    'SERVER_ADDR'       => '127.0.0.1',
    'SERVER_PORT'       => '80',
    'SERVER_NAME'       => php_uname('n'),
    'SERVER_PROTOCOL'   => 'HTTP/1.1',
    'CONTENT_TYPE'      => '',
    'CONTENT_LENGTH'    => 0
), false);
