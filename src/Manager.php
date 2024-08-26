<?php

declare(strict_types = 1);

namespace lifetime\schedule;

use Exception;
use Swoole\Event;
use Swoole\Process;
use Swoole\Timer;

/**
 * 服务管理
 */
class Manager
{
    /**
     * 启动服务
     * @access  public
     * @return void
     */
    public function start()
    {
        if (Config::getUseMultipleMode()) {
            (new ServiceMultipleProcess)->start();
        } else {
            (new ServiceSingleProcess)->start();
        }
    }

    /**
     * 停止服务
     * @access  public
     * @return void
     */
    public function stop()
    {
        if (!$this->isRunning()) {
            throw new Exception('Service not started!');
        }
        echo 'Stopping service...' . PHP_EOL;
        Process::kill($this->getPid(), SIGTERM);
        $num = 0;
        Timer::tick(10, function($timerId) use(&$num){
            if (!$this->isRunning()) {
                echo '> Success' . PHP_EOL;
                Timer::clear($timerId);
            }
            if ($num > 10000) {
                echo '> Failure' . PHP_EOL;
                Timer::clear($timerId);
            }
            $num += 10;
        });
        Event::wait();
    }
    /**
     * 重新加载
     * @access  public
     * @return void
     */
    public function reload()
    {
        if (!$this->isRunning()) {
            throw new Exception('Service not started!');
        }
        echo 'Reloading service...' . PHP_EOL;
        Process::kill($this->getPid(), SIGUSR1);
        if (!$this->isRunning()) {
            echo '> Failure';
        } else {
            echo '> Success';
        }
        echo PHP_EOL;
    }
    /**
     * 重启服务
     * @access public
     * @return void
     */
    public function restart()
    {
        $this->stop();
        $this->start();
    }
    /**
     * 服务状态
     * @access  public
     * @return void
     */
    public function state()
    {
        if ($this->isRunning()) {
            echo '> Service Running';
        } else {
            echo '> Service Stop';
        }
        echo PHP_EOL;
    }

    /**
     * 获取服务器PID
     * @access  protected
     * @return bool|int
     */
    protected function getPid()
    {
        return (int)@file_get_contents(Config::getPidFile());
    }
    /**
     * 判断服务是否正在运行
     * @access  protected
     * @return bool
     */
    protected function isRunning(): bool
    {
        $pid = $this->getPid();
        if (empty($pid)) return false;
        return Process::kill($pid, 0);
    }
}