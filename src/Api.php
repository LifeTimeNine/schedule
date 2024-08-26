<?php

declare(strict_types = 1);

namespace lifetime\schedule;

use lifetime\schedule\Config;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * API基础类
 */
abstract class Api
{
    /**
     * 请求Query参数
     * @var array
     */
    protected $query = [];

    /**
     * 请求Body参数
     * @var array
     */
    protected $body = [];

    /**
     * 响应状态码
     * @var int
     */
    private $responseCode;

    /**
     * 是否请求成功
     * @var bool
     */
    private $requestSuccess = false;

    /**
     * 请求结果
     * @var array|null
     */
    private $result;

    /**
     * 发送请求
     * @access  public
     * @return  static
     */
    public function send(): self
    {
        $result = $this->request(
            static::getMethod(),
            static::getUri(),
            $this->query,
            json_encode($this->body, JSON_UNESCAPED_UNICODE),
            ['Content-Type' => 'application/json']
        );
        if ($this->responseCode <> 200) {
            return $this;
        }
        $result = json_decode($result, true);
        if (json_last_error() > 0) {
            return $this;
        }
        $this->requestSuccess = true;
        $this->result = $result;
        return $this;
    }

    /**
     * 获取请求是否成功
     * @access  public
     * @return  bool
     */
    public function success(): bool
    {
        return $this->requestSuccess && $this->result['success'];
    }

    /**
     * 获取请求结果
     * @access  public
     * @return  array
     */
    public function result(): array
    {
        return $this->result['data'] ?? [];
    }

    /**
     * 获取消息
     * @access public
     * @return  string
     */
    public function message(): string
    {
        return $this->result['message'] ?? 'Request exception';
    }

    /**
     * 发起HTTP请求
     * @access  private
     * @param   string  $method     请求方法
     * @param   string  $url        请求地址
     * @param   array   $query      Query参数
     * @param   string  $body       Body参数
     * @param   array   $header     请求头
     * @return  string
     */
    private function request(string $method, string $url, array $query = [], string $body = null, array $header = [])
    {
        if (!empty($query)) {
            $url .= (stripos($url, '?') !== false ? '&' : '?') . http_build_query($query);
        }
        if (Coroutine::getCid() <> -1) {
            $client = new Client('unix://' . Config::getHttpServerUnixSocketPath());
            $client->setMethod($method);
            $client->setData($body);
            $client->setHeaders($header);
            $client->execute($url);
            $this->responseCode = $client->getStatusCode();
            $content = $client->getBody();
        } else {
            $curl = curl_init();
            if (!empty($header)) {
                $headerData = [];
                foreach($header as $k => $v) $headerData[] = "{$k}: {$v}";
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData);
            }
            if (!empty($body)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_URL, "http://0.0.0.0{$url}");
            curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, Config::getHttpServerUnixSocketPath());
            curl_setopt($curl, CURLOPT_TIMEOUT, 2);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $content = curl_exec($curl);
            $this->responseCode = curl_getinfo($curl)['http_code'];
            curl_close($curl);
        }
        return $content;
    }

    /**
     * 响应
     * @access  protected
     * @param   \Swoole\Http\Response   $response   响应类
     * @param   array                   $data       数据
     * @param   bool                    $success    是否成功
     * @param   string                  $message    消息
     * @return  void
     */
    protected static function response(Response $response, array $data = [], bool $success = true, string $message = 'Success')
    {
        $response->end(json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取请求方法
     * @access  public
     * @return  string
     */
    abstract public static function getMethod(): string;

    /**
     * 获取请求地址
     * @access  public
     * @return  string
     */
    abstract public static function getUri(): string;

    /**
     * 处理函数
     * @access  public
     * @param   \Swoole\Http\Request    $request    请求类
     * @param   \Swoole\Http\Response   $response   响应类
     * @param   ServiceInterface        $service    服务类
     * @return  void
     */
    abstract public static function handler(Request $request, Response $response, ServiceInterface $service);
}
