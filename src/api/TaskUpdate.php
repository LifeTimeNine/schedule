<?php

declare(strict_types = 1);

namespace lifetime\schedule\api;

use lifetime\schedule\Api;
use lifetime\schedule\ServiceInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * 修改任务
 */
class TaskUpdate extends Api
{
    /**
     * 获取请求方法
     * @access  public
     * @return  string
     */
    public static function getMethod(): string
    {
        return 'POST';
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
            self::response($response, [], false, 'Missing parameter Id');
            return;
        }
        $body = json_decode($request->getContent(), true);
        if (json_last_error() > 0) {
            self::response($response, [], false, 'Data parsing failed');
            return;
        }
        if (!isset($body['loop'])) {
            self::response($response, [], false, 'Missing parameter Loop');
            return;
        }
        if (!isset($body['command'])) {
            self::response($response, [], false, 'Missing parameter Command');
            return;
        }
        if (strlen($body['command']) > 1024) {
            self::response($response, [], false, 'Command exceeds the maximum word count limit');
            return;
        }
        if ($body['loop'] && !isset($body['cron'])) {
            self::response($response, [], false, 'Missing parameter Cron');
            return;
        }
        // 判断ID是否存在
        if (empty($id) || !$service->getTaskTable()->exist($id)) {
            self::response($response, [], false, 'ID not exists');
            return;
        }
        $service->getTaskTable()->update(
            $id,
            $body['loop'],
            $body['command'],
            $body['loop'] ? trim($body['cron']) : '',
            $body['single'] ?? true
        );
        self::response($response);
    }

    /**
     * 构造函数
     * @access  public
     * @param   string  $id         任务ID
     * @param   bool    $loop       是否是循环任务
     * @param   string  $command    命令
     * @param   string  $cron       Cron表达式
     * @param   bool    $single       是否限制单个运行
     */
    public function __construct(string $id, bool $loop, string $command, string $cron, bool $single = true)
    {
        $this->query = ['id' => $id];
        $this->body = ['loop' => $loop, 'command' => $command, 'cron' => $cron, 'single' => $single];
    }
}