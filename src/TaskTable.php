<?php

declare(strict_types = 1);

namespace lifetime\schedule;

use Countable;
use Iterator;
use Swoole\Table;

/**
 * 任务信息表
 */
class TaskTable implements Iterator, Countable
{
    /**
     * 计划任务信息内存表
     * @var \Swoole\Table
     */
    protected $table;

    /**
     * 构造函数
     * @access  public
     * @param   int     $size   任务表大小
     */
    public function __construct(int $size = 1024)
    {
        // 创建内存表
        $this->table = new Table($size);
        $this->table->column('command', Table::TYPE_STRING, 1024);
        $this->table->column('loop', Table::TYPE_INT, 1);
        $this->table->column('cron', Table::TYPE_STRING, 64);
        $this->table->column('single', Table::TYPE_INT, 1);
        $this->table->column('second_range', Table::TYPE_INT);
        $this->table->column('minute_range', Table::TYPE_INT);
        $this->table->column('hour_range', Table::TYPE_INT, 3);
        $this->table->column('day_range', Table::TYPE_INT, 4);
        $this->table->column('month_range', Table::TYPE_INT, 2);
        $this->table->column('week_range', Table::TYPE_INT, 1);
        $this->table->column('running', Table::TYPE_INT, 1);
        $this->table->column('enable', Table::TYPE_INT, 1);
        $this->table->column('running_number', Table::TYPE_INT);
        $this->table->column('success_number', Table::TYPE_INT);
        $this->table->column('fail_number', Table::TYPE_INT);
        $this->table->column('last_running_time', Table::TYPE_INT);
        $this->table->column('next_running_time', Table::TYPE_INT);
        $this->table->create();
    }

    /**
     * 检查某个ID是否存在
     * @access  public
     * @param   string  $id     ID
     * @return  bool
     */
    public function exist(string $id): bool
    {
        return $this->table->exist($id);
    }

    /**
     * 添加
     * @access  public
     * @param   string  $id         ID
     * @param   bool    $loop       是否是循环任务
     * @param   string  $command    命令
     * @param   string  $cron       Cron表达式
     * @param   bool    $single     是否是单个运行
     * @return  bool
     */
    public function add(string $id, bool $loop, string $command, string $cron, bool $single): bool
    {
        if ($loop) {
            $cronObject = Cron::parse($cron);
        } else {
            $cronObject = null;
        }
        return $this->table->set($id, [
            'loop' => $loop ? 1 : 0,
            'command' => $command,
            'cron' =>  $loop ? trim($cron) : '',
            'single' => $single ? 1 : 0 ,
            'second_range' => $loop ? $cronObject->getSecondRange(): 0,
            'minute_range' => $loop ? $cronObject->getMinuteRange() : 0,
            'hour_range' => $loop ? $cronObject->getHourRange(): 0,
            'day_range' => $loop ? $cronObject->getDayRange() : 0,
            'month_range' => $loop ? $cronObject->getMonthRange() : 0,
            'week_range' => $loop ? $cronObject->getWeekRange() : 0,
            'running' => 0,
            'enable' => 1,
            'running_number' => 0,
            'success_number' => 0,
            'fail_number' => 0,
            'last_running_time' => 0,
            'next_running_time' => $loop ? $cronObject->getNextRunningTime() : 0
        ]);
    }

    /**
     * 修改
     * @access  public
     * @param   string  $id         ID
     * @param   bool    $loop       是否是循环任务
     * @param   string  $command    命令
     * @param   string  $cron       Cron表达式
     * @param   bool    $single     是否是单个运行
     * @return  bool
     */
    public function update(string $id, bool $loop, string $command, string $cron, bool $single): bool
    {
        if ($loop) {
            $cronObject = Cron::parse($cron);
        } else {
            $cronObject = null;
        }
        return $this->table->set($id, array_merge($this->table->get($id), [
            'loop' => $loop ? 1 : 0,
            'command' => $command,
            'cron' =>  $loop ? trim($cron) : '',
            'single' => $single ? 1 : 0 ,
            'second_range' => $loop ? $cronObject->getSecondRange(): 0,
            'minute_range' => $loop ? $cronObject->getMinuteRange() : 0,
            'hour_range' => $loop ? $cronObject->getHourRange(): 0,
            'day_range' => $loop ? $cronObject->getDayRange() : 0,
            'month_range' => $loop ? $cronObject->getMonthRange() : 0,
            'week_range' => $loop ? $cronObject->getWeekRange() : 0,
        ]));
    }

    /**
     * 获取
     * @access  public
     * @param   string  $id ID
     * @return  array
     */
    public function get(string $id): array
    {
        $result = $this->table->get($id);
        $result['loop'] = $result['loop'] == 1;
        $result['single'] = $result['single'] == 1;
        $result['cron'] = $result['cron'] ?: null;
        $result['enable'] = $result['enable'] == 1;
        $result['last_running_time'] = $result['last_running_time'] ?: null;
        return $result;
    }

    /**
     * 删除
     * @access  public
     * @param   string  $id 任务ID
     * @return  bool
     */
    public function delete(string $id): bool
    {
        return $this->table->del($id);
    }

    /**
     * 修改运行状态
     * @access  public
     * @param   string  $id         ID
     * @param   bool    $running    是否在运行
     * @return  bool
     */
    public function updateRunning(string $id, bool $running): bool
    {
        return $this->table->set($id, array_merge($this->table->get($id), [
            'running' => $running ? 1 : 0
        ]));
    }

    /**
     * 修改下一次运行时间
     * @access  public
     * @param   string  $id                 ID
     * @param   int     $nextRunningTime    下一次运行时间
     * @return  bool
     */
    public function updateNextRunningTime(string $id, int $nextRunningTime): bool
    {
        return $this->table->set($id, array_merge($this->table->get($id), [
            'next_running_time' => $nextRunningTime
        ]));
    }

    /**
     * 更新结果
     * @access  public
     * @param   string  $id         ID
     * @param   bool    $success    是否运行成功
     * @param   int     $startTime  运行时间
     * @return  bool
     */
    public function updateRunResult(string $id, bool $success, int $startTime): bool
    {
        $data = $this->table->get($id);
        return $this->table->set($id, array_merge($data, [
            'running_number' => $data['running_number'] + 1,
            'success_number' => $data['success_number']+ ($success > 0 ? 0 : 1),
            'fail_number' => $data['fail_number'] + ($success> 0 ? 1 : 0),
            'last_running_time' => (int)$startTime
        ]));
    }

    /**
     * 修改状态
     * @access  public
     * @param   string  $id     ID
     * @param   bool    $enable 是否启用
     * @return  bool
     */
    public function updateStatus(string $id, bool $enable): bool
    {
        $data = $this->table->get($id);
        return $this->table->set($id, array_merge($data, [
            'enable' => $enable ? 1 : 0
        ]));
    }

    /**
     * 获取列表
     * @access  public
     * @return  array
     */
    public function getList(): array
    {
        $result = [];
        foreach($this as $id => $item) {
            $result[] = [
                'id' => $id,
                'loop' => $item['loop'],
                'single' => $item['single'],
                'cron' => $item['cron'],
                'command' => $item['command'],
                'enable' => $item['enable']
            ];
        }
        return $result;
    }

    public function current(): array
    {
        $result = $this->table->current();
        $result['loop'] = $result['loop'] == 1;
        $result['single'] = $result['single'] == 1;
        $result['cron'] = $result['cron'] ?: null;
        $result['enable'] = $result['enable'] == 1;
        $result['last_running_time'] = $result['last_running_time'] ?: null;
        return $result;
    }

    public function key(): string
    {
        return $this->table->key();
    }

    public function next(): void
    {
        $this->table->next();
    }

    public function rewind(): void
    {
        $this->table->rewind();
    }

    public function valid(): bool
    {
        return $this->table->valid();
    }
    
    public function count(): int
    {
        return $this->table->count();
    }
}