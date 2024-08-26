<?php

declare(strict_types = 1);

namespace lifetime\schedule\api;

use lifetime\schedule\Api;
use lifetime\schedule\ServiceInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * 获取任务
 */
class TaskGet extends Api
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
        return '/task';
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
        $id = $request->get['id'] ?? null;
        if (empty($id)) {
            $result = [];
            foreach($service->getTaskTable() as $id => $item) {
                $result[] = [
                    'id' => $id,
                    'loop' => $item['loop'],
                    'command' => $item['command'],
                    'cron' => $item['cron'],
                    'single' => $item['single'],
                    'enable' => $item['enable'],
                    'running' => $item['running'],
                    'running_number' => $item['running_number'],
                    'success_number' => $item['success_number'],
                    'fail_number' => $item['fail_number'],
                    'last_running_time' => $item['last_running_time'],
                    'next_running_time' => $item['next_running_time'],
                ];
            }
        } else {
            if (empty($id) || !$service->getTaskTable()->exist($id)) {
                self::response($response, [], false, 'Task does not exist');
                return;
            }
            $item = $service->getTaskTable()->get($id);
            $result = [
                'id' => $id,
                'loop' => $item['loop'],
                'command' => $item['command'],
                'cron' => $item['cron'],
                'single' => $item['single'],
                'enable' => $item['enable'],
                'running' => $item['running'],
                'running_number' => $item['running_number'],
                'success_number' => $item['success_number'],
                'fail_number' => $item['fail_number'],
                'last_running_time' => $item['last_running_time'],
                'next_running_time' => $item['next_running_time'],
            ];
        }
        self::response($response, $result);
    }

    /**
     * 构造函数
     * @access  public
     * @param   string  $id     任务ID
     */
    public function __construct(string $id = null)
    {
        if (!empty($id)) $this->query = ['id' => $id];
    }
}