# Schedule

一个管理计划任务的库

## 引入
~~~
composer require lifetime/schedule
~~~

## 配置
配置是全局生效的，只需要初始化一次就可以
~~~php
\lifetime\schedule\Config::initiate([
  // PID文件
  'pid_file' => null,
  // 守护进程运行
  'daemon' => false,
  // 事件类
  'event' => null,
  // 任务表大小
  'task_table_size' => 1024,
  // 使用多进程模式
  'use_multiple_mode' => true
]);
~~~

也可以单独设置某个配置项
~~~php
// 设置服务以守护进程的方式运行
\lifetime\schedule\Config::setDaemon(true);
// 设置任务表大小
\lifetime\schedule\Config::setTaskTableSize(2048);
~~~

## 服务

服务由`lifetime\schedule\Manager`统一管理

### 启动服务
~~~php
(new lifetime\schedule\Manager)->start();
~~~

### 停止服务
~~~php
(new lifetime\schedule\Manager)->stop();
~~~

> 停止服务会退出任务的监护进程，正在执行的任务不会退出，在任务执行完成后，会自行退出

### 重载服务
~~~php
(new lifetime\schedule\Manager)->reload();
~~~

> 多进程模式下, 重载只会重启HTTP服务和定时器
> 单进程模式下, 除了重启HTTP服务和定时器外, 还会重启事件和任务处理协程

### 重启服务
~~~php
(new lifetime\schedule\Manager)->restart();
~~~

### 服务状态
~~~php
(new lifetime\schedule\Manager)->state();
~~~

## 管理

### 运行任务
~~~php
$api = (new lifetime\schedule\api\SystemRun('task_1'))->send();
// 请求是否成功
var_dump($api->success());
// 返回消息
var_dump($api->message());
// 返回结果
var_dump($api->result());
~~~

### 服务状态
~~~php
$api = (new lifetime\schedule\api\SystemStatus())->send();
// 请求是否成功
var_dump($api->success());
// 返回消息
var_dump($api->message());
// 返回结果
var_dump($api->result());
~~~

### 创建任务
~~~php
$api = (new lifetime\schedule\api\TaskCreate('task_1', true, 'echo 123', '0 * * * * *', true))->send();
// 请求是否成功
var_dump($api->success());
// 返回消息
var_dump($api->message());
// 返回结果
var_dump($api->result());
~~~

### 创建任务
~~~php
$api = (new lifetime\schedule\api\TaskDelete('task_1'))->send();
// 请求是否成功
var_dump($api->success());
// 返回消息
var_dump($api->message());
// 返回结果
var_dump($api->result());
~~~

如果返回失败，异常日志中出现`Swoole\Table::set(): failed to set('xxx'), unable to allocate memory`的异常，是因为任务表太小，请调整`task_table_size`的值

### 获取任务
~~~php
$api = (new lifetime\schedule\api\TaskGet('task_1'))->send();
// 请求是否成功
var_dump($api->success());
// 返回消息
var_dump($api->message());
// 返回结果
var_dump($api->result());
~~~

### 更新任务
~~~php
$api = (new lifetime\schedule\api\TaskUpdate('task_1', true, 'echo 123', '0 * * * * *', true))->send();
// 请求是否成功
var_dump($api->success());
// 返回消息
var_dump($api->message());
// 返回结果
var_dump($api->result());
~~~

## 事件
所有的事件都是在协程环境下且按顺序执行的，因此不使用连接池也可以正常使用

实现接口类`lifetime\schedule\EventInterface`的所有方法, 在`\lifetime\schedule\Config`中设置事件类

### 系统启动事件
~~~php
onStart(): void
~~~

### 系统关闭事件
~~~php
onClose(array $taskList): void
~~~

### 任务开始执行事件
~~~php
onTaskStart(string $taskId): void
~~~

### 任务执行结束事件
~~~php
onTaskEnd(string $taskId, int $startTime, int $endTime, bool $success, string $output, string $runtime, int $nextRunningTime): void
~~~

### 系统异常事件
~~~php
onError(Throwable $e): void
~~~
