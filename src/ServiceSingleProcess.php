<?php

declare(strict_types = 1);

namespace lifetime\schedule;

use lifetime\schedule\api\SystemRun;
use lifetime\schedule\api\SystemStatus;
use lifetime\schedule\api\TaskCreate;
use lifetime\schedule\api\TaskDelete;
use lifetime\schedule\api\TaskGet;
use lifetime\schedule\api\TaskUpdate;
use lifetime\schedule\exception\Exception;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Http\Server;
use Swoole\Event;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Timer;

/**
 * 单进程服务
 */
class ServiceSingleProcess implements ServiceInterface
{
    /**
     * HTTP服务
     * @var Server
     */
    protected $httpServer;

    /**
     * 计划任务信息内存表
     * @var TaskTable
     */
    protected $taskTable;

    /**
     * 事件消息通道
     * @var Channel
     */
    protected $eventChannel;

    /**
     * 任务运行消息通道
     * @var Channel
     */
    protected $taskRunChannel;

    /**
     * HTTP API列表
     * @var array
     */
    protected $httpApiList = [
        SystemRun::class,
        SystemStatus::class,
        TaskCreate::class,
        TaskDelete::class,
        TaskGet::class,
        TaskUpdate::class,
    ];

    /**
     * HTTP路由映射表
     * @var array
     */
    protected $httpRouteMap = [];

    /**
     * 构造函数
     * @access  public
     */
    public function __construct()
    {
        foreach($this->httpApiList as $item) {
            $key = call_user_func([$item, 'getUri']) . '|' . call_user_func([$item, 'getMethod']);
            $this->httpRouteMap[$key] = $item;
        }
    }

    /**
     * 创建事件消息通道
     * @access  protected
     * @return  void
     */
    protected function createEventChannel()
    {
        $this->eventChannel = new Channel(1024);
    }


    /**
     * 创建任务运行消息通道
     * @access  protected
     * @return void
     */
    protected function createTaskRunningChannel()
    {
        $this->taskRunChannel = new Channel(1024);
    }

    /**
     * 发送任务运行消息
     * @access  public
     * @param   array  $idList     任务ID列表
     * @return  void
     */
    public function sendTaskRunningMessage(array $idList)
    {
        $this->taskRunChannel->push($idList);
    }

    /**
     * 触发事件
     * @access  protected
     * @param   string  $event      事件
     * @param   array   $params     参数列表
     * @return  void
     */
    protected function event(string $event, array $params = [])
    {
        $this->eventChannel->push(['event' => $event, 'params' => $params]);
    }

    /**
     * 获取计划任务内存表
     * @access  public
     * @return  TaskTable
     */
    public function getTaskTable(): TaskTable
    {
        return $this->taskTable;
    }

    /**
     * 创建HTTP服务
     * @access  protected
     * @return  int
     */
    protected function createHttpServer(): int
    {
        return Coroutine::create(function() {
            unlink(Config::getHttpServerUnixSocketPath());
            $this->httpServer = new Server('unix://' . Config::getHttpServerUnixSocketPath());
            $this->httpServer->handle('/', function(Request $request, Response $response) {
                $response->header('Content-Type', 'application/json');
                $key = "{$request->server['request_uri']}|{$request->server['request_method']}";
                if (array_key_exists($key, $this->httpRouteMap)) {
                    try {
                        call_user_func_array([$this->httpRouteMap[$key], 'handler'], [$request, $response, $this]);
                    } catch (Exception $e) {
                        $response->end(json_encode([
                            'success' => false,
                            'message' => $e->getMessage(),
                            'data' => []
                        ], JSON_UNESCAPED_UNICODE));
                    } catch (\Throwable $e) {
                        $response->end(json_encode([
                            'success' => false,
                            'message' => 'Service internal exception',
                            'data' => []
                        ], JSON_UNESCAPED_UNICODE));
                        $this->event('error', [$e]);
                    }
                } else {
                    $response->end(json_encode([
                        'success' => false,
                        'message' => 'Route does not exist',
                        'data' => []
                    ], JSON_UNESCAPED_UNICODE));
                }
            });
            $this->httpServer->start();
        });
    }

    /**
     * 创建任务运行协程
     * @access  protected
     * @return  int
     */
    protected function createTaskRunCoroutine(): int
    {
        return Coroutine::create(function() {
            while(true) {
                $idList = $this->taskRunChannel->pop();
                if ($idList === false) break;
                $coroutineList = [];
                foreach($idList as $id) {
                    $coroutineList[$id] = function() use ($id) {
                        $task = $this->getTaskTable()->get($id);
                        if (empty($task)) return;
                        $this->getTaskTable()->updateRunning($id, true);
                        $this->event('taskStart', [$id]);
                        $startTime = microtime(true);
                        $result = Coroutine::exec($task['command'], true);
                        $endTime = microtime(true);
                        $runtime = number_format(($endTime - $startTime), 4);
                        $this->getTaskTable()->updateRunning($id, false);
                        $this->getTaskTable()->updateRunResult($id, $result['code'] == 0, (int)$startTime);
                        $this->event('taskEnd', [
                            $id,
                            (int)$startTime,
                            (int)$endTime,
                            $result['code'] == 0,
                            $result['output'],
                            $runtime,
                            $this->getTaskTable()->get($id)['next_running_time']
                        ]);
                    };
                }
                Coroutine\batch($coroutineList);
            }
        });
    }

    /**
     * 创建事件处理协程
     * @access  protected
     * @return  int
     */
    protected function createEventCoroutine(): int
    {
        return Coroutine::create(function() {
            while(($item = $this->eventChannel->pop()) !== false) {
                try {
                    call_user_func_array([Config::getEvent(), 'on'. ucfirst($item['event'])], $item['params']);
                } catch (\Throwable $th) {
                    if ($item['event'] <> 'error') {
                        $this->event('error', [$th]);
                    }
                }
            }
        });
    }

    /**
     * 创建定时器
     * @access  protected
     * @return  int
     */
    protected function createTimer(): int
    {
        return Timer::tick(1000, function() {
            $time = time();
            $runningIdList = [];
            foreach($this->getTaskTable() as $id => $item) {
                if (!$item['loop'] || !$item['enable']) continue;
                if($item['next_running_time'] == $time) {
                    $cron = new Cron(
                        $item['second_range'],
                        $item['minute_range'],
                        $item['hour_range'],
                        $item['day_range'],
                        $item['month_range'],
                        $item['week_range']
                    );
                    $this->getTaskTable()->updateNextRunningTime($id, $cron->getNextRunningTime(time() + 1));
                    if (($item['single'] && !$item['running']) || !$item['single']) {
                        $runningIdList[] = $id;
                    }
                }
            }
            if (count($runningIdList) > 0) $this->sendTaskRunningMessage($runningIdList);
        });
    }

    /**
     * 启动服务
     * @access  public
     * @return  void
     */
    public function start()
    {
        $process = new Process(function(Process $process) {
            // 设置进程名称
            $process->name('php schedule');
            // 设置守护进程
            if (Config::getDaemon()) {
                $process->daemon();
            }
            // 创建PID文件目录
            if (!is_dir(dirname(Config::getPidFile()))) mkdir(dirname(Config::getPidFile()));
            // 写入PID
            file_put_contents(Config::getPidFile(), getmypid());

            // 设置超时时间
            Coroutine::set([
                'socket_timeout' => -1,
                'socket_connect_timeout' => 5,
                'socket_read_timeout' => -1,
                'socket_write_timeout' => 5,
            ]);

            // 创建事件消息通道
            $this->createEventChannel();
            // 创建任务运行消息通道
            $this->createTaskRunningChannel();

            // 创建计划任务内存表
            $this->taskTable = new TaskTable(Config::getTaskTableSize());

            // 创建事件处理协程
            $eventCoroutineId = $this->createEventCoroutine();
            // 创建任务运行协程
            $taskRunCoroutineId = $this->createTaskRunCoroutine();
            // 创建定时器
            $timerId = $this->createTimer();
            // 创建HTTP服务
            $httpServerCoroutineId = $this->createHttpServer();

            // 停止信号
            Process::signal(SIGTERM, function() use (&$taskRunCoroutineId, &$httpServerCoroutineId, &$timerId) {
                // 关闭HTTP服务
                $this->httpServer->shutdown();
                while(Coroutine::exists($httpServerCoroutineId)) Coroutine::sleep(0.05);
                // 取消HTTP服务协程
                Coroutine::cancel($httpServerCoroutineId);
                // 清除定时器
                Timer::clear($timerId);
                // 关闭任务运行消息通道
                $this->taskRunChannel->close();
                // 取消任务处理协程
                Coroutine::cancel($taskRunCoroutineId);
                // 触发系统关闭事件
                $this->event('close', [$this->taskTable->getList()]);
                // 关闭事件消息通道
                $this->eventChannel->close();
            });
            // 重新加载信号
            Process::signal(SIGUSR1, function() use (&$eventCoroutineId, &$taskRunCoroutineId, &$httpServerCoroutineId, &$timerId) {
                // 关闭HTTP服务
                $this->httpServer->shutdown();
                // 取消HTTP服务协程
                Coroutine::cancel($httpServerCoroutineId);
                // 清除定时器
                Timer::clear($timerId);
                // 取消任务处理协程
                Coroutine::cancel($taskRunCoroutineId);
                // 取消事件协程
                Coroutine::cancel($eventCoroutineId);

                // 创建事件消息通道
                $this->createEventChannel();
                // 创建事件处理协程
                $eventCoroutineId = $this->createEventCoroutine();
                // 创建任务运行协程
                $taskRunCoroutineId = $this->createTaskRunCoroutine();
                // 创建定时器
                $timerId = $this->createTimer();
                // 创建HTTP服务
                $httpServerCoroutineId = $this->createHttpServer();
            });
            // 触发系统启动事件
            $this->event('start');

        }, false, 0, true);
        $process->start();
        Process::wait();
    }
}