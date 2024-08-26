<?php

declare(strict_types = 1);

namespace lifetime\schedule;

/**
 * 事件接口类
 */
interface EventInterface
{
    /**
     * 系统启动事件
     * @access  public
     * @return  void
     */
    public function onStart();

    /**
     * 系统关闭事件
     * @access  public
     * @param   array   $taskList   任务列表
     * @return  void
     */
    public function onClose(array $taskList);

    /**
     * 任务开始执行事件
     * @access  public
     * @param   string  $taskId     任务ID
     * @return  void
     */
    public function onTaskStart(string $taskId);

    /**
     * 任务执行结束事件
     * @access  public
     * @param   string  $taskId             任务ID
     * @param   int     $startTime          结束时间
     * @param   int     $endTime            结束时间
     * @param   bool    $success            是否成功
     * @param   string  $output             输出内容
     * @param   string  $runtime            运行时长
     * @param   int     $nextRunningTime    下一次运行时间
     * @return  void
     */
    public function onTaskEnd(string $taskId, int $startTime, int $endTime, bool $success, string $output, string $runtime, int $nextRunningTime);

    /**
     * 系统异常事件
     * @access  public
     * @param   Throwable   $e      异常
     * @return  void
     */
    public function onError(\Throwable $e);
}