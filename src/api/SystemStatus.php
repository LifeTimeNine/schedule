<?php

declare(strict_types = 1);

namespace lifetime\schedule\api;

use lifetime\schedule\Api;
use lifetime\schedule\ServiceInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * 获取系统状态
 */
class SystemStatus extends Api
{
    /**
     * 获取请求方法
     * @access  public
     * @return  string
     */
    public static function getMethod(): string
    {
        return 'GET';
    }

    /**
     * 获取请求地址
     * @access  public
     * @return  string
     */
    public static function getUri(): string
    {
        return '/system/status';
    }

    /**
     * 处理函数
     * @access  public
     * @param   \Swoole\Http\Request    $request    请求类
     * @param   \Swoole\Http\Response   $response   响应类
     * @param   ServiceInterface        $service    服务类
     * @return  void
     */
    public static function handler(Request $request, Response $response, ServiceInterface $service)
    {
        $result = [
            'total' => 0,
            'running' => 0,
            'wait' => 0
        ];
        foreach($service->getTaskTable() as $item) {
            $result['total']++;
            if ($item['running'] == 0) {
                $result['wait']++;
            } else {
                $result['running']++;
            }
        }
        static::response($response, $result);
    }
}