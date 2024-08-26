<?php

declare(strict_types = 1);

namespace lifetime\schedule;

/**
 * 配置管理类
 */
class Config
{
    /**
     * 配置
     * @var array
     */
    protected static $config = [
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
    ];

    /**
     * 初始化配置
     * @access  public
     * @param   array   $config     配置
     * @return  void
     */
    public static function initiate(array $config)
    {
        self::setPidFile($config['pid_file'] ?? ('/tmp/' . md5(__NAMESPACE__) . '.pid'));
        if (isset($config['daemon'])) self::setDaemon($config['daemon']);
        if (isset($config['event'])) self::setEvent($config['event']);
        if (isset($config['task_table_size'])) self::setTaskTableSize($config['task_table_size']);
    }

    /**
     * 获取HTTP服务Unix套接字文件地址
     * @access  public
     * @return  string
     */
    public static function getHttpServerUnixSocketPath(): string
    {
        return '/tmp/' . md5(__NAMESPACE__) . '.sock';
    }

    /**
     * 获取任务进程Unix套接字文件地址
     * @access  public
     * @return  string
     */
    public static function getTaskProcessUnixSocketPath(): string
    {
        return '/tmp/' . md5(__NAMESPACE__ . '/task') . '.sock';
    }

    /**
     * 获取事件进程Unix套接字文件地址
     * @access  public
     * @return  string
     */
    public static function getEventProcessUnixSocketPath(): string
    {
        return '/tmp/' . md5(__NAMESPACE__ . '/event') . '.sock';
    }

    /**
     * 设置PID文件地址
     * @access  public
     * @param   string  $pidFile    PID文件地址
     * @return  void
     */
    public static function setPidFile(string $pidFile): void
    {
        self::$config['pid_file'] = $pidFile;
    }

    /**
     * 获取PID文件地址
     * @access  public
     * @return  string
     */
    public static function getPidFile(): string
    {
        return self::$config['pid_file'] ?: md5(__NAMESPACE__) . '.pid';
    }

    /**
     * 设置守护进程运行
     * @access  public
     * @param   bool    $daemon     守护进程运行
     * @return  void
     */
    public static function setDaemon(bool $daemon): void
    {
        self::$config['daemon'] = $daemon;
    }

    /**
     * 获取守护进程运行
     * @access  public
     * @return  bool
     */
    public static function getDaemon(): bool
    {
        return self::$config['daemon'];
    }

    /**
     * 设置事件类
     * @access  public
     * @param   EventInterface  $event  事件类
     * @return  void
     */
    public static function setEvent(EventInterface $event)
    {
        self::$config['event'] = $event;
    }

    /**
     * 获取事件类
     * @access  public
     * @return  EventInterface
     */
    public static function getEvent(): ?EventInterface
    {
        return self::$config['event'];
    }

    /**
     * 设置任务表大小
     * @access  public
     * @param   int     $size       任务表大小
     * @return  void
     */
    public static function setTaskTableSize(int $size)
    {
        self::$config['task_table_size'] = $size;
    }

    /**
     * 获取任务表大小
     * @access  public
     * @return  int
     */
    public static function getTaskTableSize(): int
    {
        return self::$config['task_table_size'];
    }

    /**
     * 设置是否是多进程模式
     * @access  public
     * @param   bool    $useMultipleMode    使用多进程模式
     * @return  void
     */
    public static function setUseMultipleMode(bool $useMultipleMode)
    {
        self::$config['use_multiple_mode'] = $useMultipleMode;
    }

    /**
     * 获取是否是多进程模式
     * @access  public
     * @return
     */
    public static function getUseMultipleMode(): bool
    {
        return self::$config['use_multiple_mode'];
    }
}
