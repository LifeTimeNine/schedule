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
use Swoole\Coroutine\Http\Server;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine\System;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Timer;
use function Swoole\Coroutine\run;

/**
 * 服务
 */
class ServiceMultipleProcess implements ServiceInterface
{
    /**
     * 进程名称前缀
     * @var string
     */
    protected $processNamePrefix = 'schedule';

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
     * 主进程
     * @var Process
     */
    protected $masterProcess;

    /**
     * 任务进程
     * @var Process
     */
    protected $taskProcess;

    /**
     * 事件处理进程
     * @var Process
     */
    protected $eventProcess;

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
     * 发送任务运行消息
     * @access  public
     * @param   array  $idList  任务ID列表
     * @return  void
     */
    public function sendTaskRunningMessage(array $idList)
    {
        $this->createTaskProcessSocket(Config::getTaskProcessUnixSocketPath(), true)->send(serialize($idList));
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
        $this->createTaskProcessSocket(Config::getEventProcessUnixSocketPath(), true)->send(serialize(['event' => $event, 'params' => $params]));
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
     * 创建HTTP服务协程
     * @access  protected
     * @return  int
     */
    protected function createHttpServerCoroutine(): int
    {
        return Coroutine::create(function() {
            if (is_file(Config::getHttpServerUnixSocketPath())) unlink(Config::getHttpServerUnixSocketPath());
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
     * 创建任务运行进程
     * @access  protected
     * @param   array       $idList     任务ID列表
     * @return  void
     */
    protected function createTaskRunningCoroutine(array $idList)
    {
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

    /**
     * 创建事件处理进程
     * @access  protected
     * @return  void
     */
    protected function createEventProcess()
    {
        $this->eventProcess = new Process(function(Process $process) {
            // 设置进程名称
            $process->name("{$this->processNamePrefix} event");
            $socket = $this->createTaskProcessSocket(Config::getEventProcessUnixSocketPath(), false);
            // 关闭信号
            Process::signal(SIGTERM, function() use($process, &$socket) {
                $socket->close();
                $process->exit();
            });
            while(!$socket->isClosed()) {
                $client = $socket->accept();
                if (!$client) continue;
                $event = unserialize($client->recv());
                try {
                    call_user_func_array([Config::getEvent(), 'on'. ucfirst($event['event'])], $event['params']);
                } catch (\Throwable $th) {
                    if ($event['event'] <> 'error') {
                        $this->event('error', [$th]);
                    }
                }
            }
        }, false, 0, true);
        $this->eventProcess->start();
    }

    /**
     * 创建任务进程
     * @access  protected
     * @return  void
     */
    protected function createTaskProcess()
    {
        $this->taskProcess = new Process(function(Process $process) {
            // 设置进程名称
            $process->name("{$this->processNamePrefix} task");
            $socket = $this->createTaskProcessSocket(Config::getTaskProcessUnixSocketPath(), false);
            // 关闭信号
            Process::signal(SIGTERM, function() use($process, &$socket) {
                $socket->close();
                $process->exit();
            });
            while(!$socket->isClosed()) {
                $client = $socket->accept();
                if (!$client) continue;
                $message = $client->recv();
                $this->createTaskRunningCoroutine(unserialize($message));
            }
        }, false, 0, true);
        $this->taskProcess->start();
    }

    /**
     * 创建Socket
     * @access  protected
     * @param   string  $unixPath   套接字文件地址
     * @param   bool    $isClient   是否是客户端
     * @return  Socket
     */
    protected function createTaskProcessSocket(string $unixPath, bool $isClient): Socket
    {
        $socket = new Socket(AF_UNIX, SOCK_STREAM);
        $socket->setProtocol([
            'open_length_check'     => true,
            'package_max_length'    => 1024 * 1024,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,
            'package_body_offset'   => 4,
        ]);
        if ($isClient) {
            $socket->connect($unixPath);
        } else {
            $socket->bind($unixPath);
            $socket->listen(4096);
        }
        return $socket;
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
        $this->masterProcess = new Process(function(Process $process) {
            // 设置进程名称
            $process->name("{$this->processNamePrefix} master");
            // 设置守护进程
            if (Config::getDaemon()) {
                $process->daemon();
            }
            // 创建PID文件目录
            if (!is_dir(dirname(Config::getPidFile()))) mkdir(dirname(Config::getPidFile()));
            // 写入PID
            file_put_contents(Config::getPidFile(), getmypid());

            // 创建任务表
            $this->taskTable = new TaskTable(Config::getTaskTableSize());

            // 创建事件处理进程
            $this->createEventProcess();
            // 创建任务管理进程
            $this->createTaskProcess();

            run(function() use ($process){
                // 创建HTTP服务
                $httpServerCoroutineId = $this->createHttpServerCoroutine();
                // 创建定时器
                $timerId = $this->createTimer();
                // 创建回收子进程协程
                Coroutine::create(function() {
                    while(System::wait());
                });
                // 停止信号
                Process::signal(SIGTERM, function() use($process, &$httpServerCoroutineId, &$timerId) {
                    // 关闭HTTP服务
                    $this->httpServer->shutdown();
                    // 取消HTTP服务协程
                    Coroutine::cancel($httpServerCoroutineId);
                    // 关闭定时器
                    Timer::clear($timerId);
                    // 关闭任务进程
                    Process::kill($this->taskProcess->pid, SIGTERM);

                    // 服务关闭事件
                    $this->event('close', [$this->getTaskTable()->getList()]);

                    Process::kill($this->eventProcess->pid, SIGTERM);

                    $process->exit();
                });
                // 重载信号
                Process::signal(SIGUSR1, function() use(&$httpServerCoroutineId, &$timerId) {
                    // 关闭HTTP服务
                    $this->httpServer->shutdown();
                    // 取消HTTP服务协程
                    Coroutine::cancel($httpServerCoroutineId);
                    // 关闭定时器
                    Timer::clear($timerId);

                    // 事件进程重载
                    Process::kill($this->eventProcess->pid, SIGUSR1);
                    // 任务进程重载
                    Process::kill($this->taskProcess->pid, SIGUSR1);

                    // 创建HTTP服务
                    $httpServerCoroutineId = $this->createHttpServerCoroutine();
                    // 创建定时器
                    $timerId = $this->createTimer();
                });
                $this->event('start');
            });
        }, false, 0, false);
        // 设置使用消息队列
        $this->masterProcess->useQueue(1);
        $this->masterProcess->start();
        Process::wait();
    }
}