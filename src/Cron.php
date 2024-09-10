<?php

declare(strict_types = 1);

namespace lifetime\schedule;

use DateTime;
use lifetime\schedule\exception\InvalidArgumentException;

/**
 * Cron表达式类
 */
class Cron
{
    const SECOND = 0;
    const MINUTE = 1;
    const HOUR = 2;
    const DAY = 3;
    const MONTH = 4;
    const WEEKDAY = 5;

    /**
     * 秒范围
     * @var int
     */
    protected $secondRange = 0;

    /**
     * 分范围
     * @var int
     */
    protected $minuteRange = 0;

    /**
     * 时范围
     * @var int
     */
    protected $hourRange = 0;

    /**
     * 天范围
     * @var int
     */
    protected $dayRange = 0;

    /**
     * 月范围
     * @var int
     */
    protected $monthRange = 0;

    /**
     * 周范围
     * @var int
     */
    protected $weekRange = 0;

    /**
     * 构造函数
     * @access  public
     * @param   int $secondRange    秒范围
     * @param   int $minuteRange    分范围
     * @param   int $hourRange      时范围
     * @param   int $dayRange       天范围
     * @param   int $monthRange     月范围
     * @param   int $weekRange      周范围
     */
    public function __construct(int $secondRange, int $minuteRange, int $hourRange, int $dayRange, int $monthRange, int $weekRange)
    {
        $this->secondRange = $secondRange;
        $this->minuteRange = $minuteRange;
        $this->hourRange = $hourRange;
        $this->dayRange = $dayRange;
        $this->monthRange = $monthRange;
        $this->weekRange = $weekRange;
    }

    /**
     * 获取秒范围
     * @access  public
     * @return  int
     */
    public function getSecondRange(): int
    {
        return $this->secondRange;
    }

    /**
     * 获取分范围
     * @access  public
     * @return  int
     */
    public function getMinuteRange(): int
    {
        return $this->minuteRange;
    }

    /**
     * 获取时范围
     * @access  public
     * @return  int
     */
    public function getHourRange(): int
    {
        return $this->hourRange;
    }

    /**
     * 获取天范围
     * @access  public
     * @return  int
     */
    public function getDayRange(): int
    {
        return $this->dayRange;
    }

    /**
     * 获取月范围
     * @access  public
     * @return  int
     */
    public function getMonthRange(): int
    {
        return $this->monthRange;
    }

    /**
     * 获取周范围
     * @access  public
     * @return  int
     */
    public function getWeekRange(): int
    {
        return $this->weekRange;
    }

    /**
     * 验证运行
     * @access  public
     * @param   int     $timestamp  时间戳
     * @return  bool
     */
    public function verifyRunning(?int $timestamp = null): bool
    {
        if (empty($timestamp)) $timestamp = time();
        return (($this->getSecondRange() & (1 << date('s', $timestamp))) > 0)
            && (($this->getMinuteRange() & (1 << date('i', $timestamp))) > 0)
            && (($this->getHourRange() & (1 << date('H', $timestamp))) > 0)
            && (($this->getDayRange() & (1 << date('d', $timestamp))) > 0)
            && (($this->getMonthRange() & (1 << date('m', $timestamp))) > 0)
            && (($this->getWeekRange() & (1 << date('w', $timestamp))) > 0);
    }

    /**
     * 获取下一次执行时间
     * @access  public
     * @param   int $timestamp  时间戳
     * @return  int
     */
    public function getNextRunningTime(?int $timestamp = null): int
    {
        $dateTime = new DateTime();
        if (!empty($timestamp)) $dateTime->setTimestamp($timestamp);
        $this->calculateDomainTime(self::MONTH, $dateTime);
        return $dateTime->getTimestamp();
    }

    /**
     * 计算单个域的时间
     * @access  private
     * @param   int         $domain     域类型
     * @param   Datetime    $dateTime   时间
     * @param   bool        $satisfied  当前值是否满足
     * @return  void
     */
    private function calculateDomainTime(int $domain, DateTime $dateTime, bool $satisfied = true)
    {
        switch($domain) {
            case self::MONTH:
            {
                $result = $this->getMonthRange() & ((pow(2, 12) - 1) << (int)$dateTime->format('m'));
                if ($result == 0) {
                    $dateTime->modify('+1 year');
                    $dateTime->setDate((int)$dateTime->format('Y'), 1, 1);
                    $this->calculateDomainTime(self::DAY, $dateTime, false);
                    break;
                }
                $result = decbin($result);
                $result = strlen($result) - strrpos($result, '1') - 1;
                $satisfied = $result == (int)$dateTime->format('m');
                if (!$satisfied) {
                    $dateTime->setDate((int)$dateTime->format('Y'), $result, (int)$dateTime->format('d'));
                }
                if($this->getWeekRange() <> pow(2, 7) - 1) {
                    $this->calculateDomainTime(self::WEEKDAY, $dateTime, $satisfied);
                } else {
                    $this->calculateDomainTime(self::DAY, $dateTime, $satisfied);
                }
                break;
            }
            case self::DAY:
            {
                if ($satisfied) {
                    $result = $this->getDayRange() & ((pow(2, 31) - 1) << (int)$dateTime->format('d'));
                    if ($result == 0) {
                        $dateTime->modify('+1 month');
                        $dateTime->setDate((int)$dateTime->format('Y'), (int)$dateTime->format('m'), 1);
                        $this->calculateDomainTime(self::MONTH, $dateTime, true);
                        break;
                    }
                } else {
                    $result = $this->getDayRange();
                }
                $result = decbin($result);
                $result = strlen($result) - strrpos($result, '1') - 1;
                if ($result == (int)$dateTime->format('d')) {
                    $this->calculateDomainTime(self::HOUR, $dateTime, true);
                } else {
                    $dateTime->setDate((int)$dateTime->format('Y'), (int)$dateTime->format('m'), $result);
                    $this->calculateDomainTime(self::HOUR, $dateTime, false);
                }
                $satisfied = $result == (int)$dateTime->format('d');
                break;
            }
            case self::HOUR:
            {
                if ($satisfied) {
                    $result = $this->getHourRange() & ((pow(2, 24) - 1) << (int)$dateTime->format('H'));
                    if ($result == 0) {
                        $dateTime->modify('+1 day');
                        $dateTime->setTime(0, 0, 0);
                        if($this->getWeekRange() <> pow(2, 7) - 1) {
                            $this->calculateDomainTime(self::DAY, $dateTime, true);
                        } else {
                            $this->calculateDomainTime(self::WEEKDAY, $dateTime, true);
                        }
                        break;
                    }
                } else {
                    $result = $this->getHourRange();
                }
                $result = decbin($result);
                $result = strlen($result) - strrpos($result, '1') - 1;
                if ($result == (int)$dateTime->format('H')) {
                    $this->calculateDomainTime(self::MINUTE, $dateTime, true);
                } else {
                    $dateTime->setTime($result, (int)$dateTime->format('i'), (int)$dateTime->format('s'));
                    $this->calculateDomainTime(self::MINUTE, $dateTime, false);
                }
                break;
            }
            case self::MINUTE:
            {
                if ($satisfied) {
                    $result = $this->getMinuteRange() & ((pow(2, 60) - 1) << (int)$dateTime->format('i'));
                    if ($result == 0) {
                        $dateTime->modify('+1 hour');
                        $dateTime->setTime((int)$dateTime->format('H'), 0, 0);
                        $this->calculateDomainTime(self::HOUR, $dateTime, true);
                        break;
                    }
                } else {
                    $result = $this->getMinuteRange();
                }
                $result = decbin($result);
                $result = strlen($result) - strrpos($result, '1') - 1;
                if ($result == (int)$dateTime->format('i')) {
                    $this->calculateDomainTime(self::SECOND, $dateTime, true);
                } else {
                    $dateTime->setTime((int)$dateTime->format('H'), $result, (int)$dateTime->format('s'));
                    $this->calculateDomainTime(self::SECOND, $dateTime, false);
                }
                break;
            }
            case self::SECOND:
            {
                if ($satisfied) {
                    $result = $this->getSecondRange() & ((pow(2, 60) - 1) << (int)$dateTime->format('s'));
                    if ($result == 0) {
                        $dateTime->modify('+1 minute');
                        $dateTime->setTime((int)$dateTime->format('H'), (int)$dateTime->format('i'), 0);
                        $this->calculateDomainTime(self::MINUTE, $dateTime, true);
                        break;
                    }
                } else {
                    $result = $this->getSecondRange();
                }
                $result = decbin($result);
                $result = strlen($result) - strrpos($result, '1') - 1;
                $dateTime->setTime((int)$dateTime->format('H'), (int)$dateTime->format('i'), $result);
                break;
            }
            case self::WEEKDAY:
            {
                if ($satisfied) {
                    $result = $this->getWeekRange() & ((pow(2, 7) - 1) << (int)$dateTime->format('w'));
                    if ($result == 0) {
                        $result = $this->getSecondRange();
                    }
                }
                $result = decbin($result);
                $result = strlen($result) - strrpos($result, '1') - 1;
                $currentWeek = (int)$dateTime->format('w');
                if ($result == $currentWeek) {
                    $this->calculateDomainTime(self::HOUR, $dateTime, true);
                } else {
                    if ($result > $currentWeek) {
                        $diffDay = $result - $currentWeek;
                        $dateTime->modify("+{$diffDay} day");
                    } else {
                        $diffDay = 7 - $currentWeek + $result + 1;
                        $dateTime->modify("+{$diffDay} day");
                    }
                    $this->calculateDomainTime(self::HOUR, $dateTime, false);
                }
                break;
            }
        }
    }

    /**
     * 解析表达式
     * @access  public
     * @param   string  $cron   表达式
     * @return  self
     * @throws  InvalidArgumentException
     */
    public static function parse(string $cron): self
    {
        if (!preg_match_all('/(((\d{1,2}-\d{1,2}|\d{1,2}),?)+|\*|\?|\d{1,2})(\/\d{1,2})?/', $cron, $result) || count($result[0]) <> 6
        ) {
            throw new InvalidArgumentException('Incorrect cron format', 1);
        }
        return new static(
            self::parseItem($result[0][0], 0, 59),
            self::parseItem($result[0][1], 0, 59),
            self::parseItem($result[0][2], 0, 23),
            self::parseItem($result[0][3], 1, 31),
            self::parseItem($result[0][4], 1, 12),
            self::parseItem($result[0][5], 0, 6)
        );
    }

    /**
     * 解析单个表达式
     * @access  protected
     * @param   string  $cronItem   单个表达式
     * @param   int     $min        最小值
     * @param   int     $max        最大值
     * @return  int
     */
    protected static function parseItem(string $cronItem, int $min, int $max): int
    {
        preg_match('/^(((\d{1,2}-\d{1,2}|\d{1,2}),?)+|\*|\?|\d{1,2})(\/(\d{1,2}))?$/', $cronItem, $result);
        if ($result[1] == '*' || $result[1] == '?') {
            $range = range($min, $max, $result[5] ?? null ?: 1);
        } else {
            $range = [];
            foreach(explode(',', $result[1]) as $item) {
                if (strpos($item, '-') === false) {
                    $range[] = (int)$item;
                } else {
                    [$start, $end] = explode('-', $item);
                    if (empty($end)) $end = $start;
                    if ($start < $min) $start = $min;
                    if ($end > $max) $end = $max;
                    $range = array_merge($range, range($start, $end, $result[5] ?? null ?: 1));
                }
            }
        }
        $res = 0;
        foreach($range as $item) {
            $res |= 1 << $item;
        }
        return $res;
    }
}