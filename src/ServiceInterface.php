<?php

declare(strict_types = 1);

namespace lifetime\schedule;

/**
 * 服务基类
 */
interface ServiceInterface
{
    /**
     * 发送任务运行消息
     * @access  public
     * @param   array  $idList  任务ID列表
     * @return  void
     */
    public function sendTaskRunningMessage(array $idList);

    /**
     * 获取计划任务内存表
     * @access  public
     * @return  TaskTable
     */
    public function getTaskTable(): TaskTable;

    /**
     * 启动服务
     * @access  public
     * @return  void
     */
    public function start();
}