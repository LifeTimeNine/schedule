<?php

declare(strict_types = 1);

namespace lifetime\schedule\api;

use lifetime\schedule\Api;
use lifetime\schedule\ServiceInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * 删除任务
 */
class TaskDelete extends Api
{
    /**
     * 获取请求方法
     * @access  public
     * @return  string
     */
    public static function getMethod(): string
    {
        return 'DELETE';
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
        if (empty($id) || !$service->getTaskTable()->exist($id)) {
            self::response($response, [], false, 'Task does not exist');
            return;
        }
        $service->getTaskTable()->delete($id);
        self::response($response);
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