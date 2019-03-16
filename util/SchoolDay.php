<?php

namespace Utility;

require_once 'SchoolDay.php';
require_once 'Exceptions.php';
require_once __DIR__.'/../config/Phrases.php';

use DateTime;
use HwBot\Phrases;

class SchoolDay
{

    public const DEFAULT_FORMAT = 'd.m';
    private const WEEKDAYS_DESC = [
        'пн' => 'Mon',
        'вт' => 'Tue',
        'ср' => 'Wed',
        'чт' => 'Thu',
        'пт' => 'Fri',
        'сб' => 'Sat',
        'вс' => 'Sun',
    ];
    private const MONTH_DESC = [
        'янв' => 1,
        'фев' => 2,
        'мар' => 3,
        'апр' => 4,
        'мая' => 5,
        'июн' => 6,
        'июл' => 7,
        'авг' => 8,
        'сеп' => 9,
        'окт' => 10,
        'ноя' => 11,
        'дек' => 12,
        '1' => 'января',
        '2' => 'февраля',
        '3' => 'марта',
        '4' => 'апреля',
        '5' => 'мая',
        '6' => 'июня',
        '7' => 'июля',
        '8' => 'августа',
        '9' => 'сентября',
        '10' => 'октября',
        '11' => 'ноября',
        '12' => 'декабря',
    ];

    private $date;
    private $has_lessons = false;
    private $subjects = [];
    private $is_null;
    private $date_string = null;

    public function __construct(DateTime $date = null)
    {
        $this->date = $date;
        $this->is_null = $date === null;

        if (!$this->is_null)
            $this->update();
    }

    /**
     * Обновляет набор предметов и наличие уроков. Устанавливает строку даты в null
     * Использовать после обновления даты
     */
    private function update()
    {
        if (!$this->is_null) {
            $this->subjects = SCHEDULE[$this->date->format('D')];
            $this->has_lessons = Weekends::is_working($this) && !empty($this->subjects);
        }
    }

    /**
     * Проверяет, содержит ли объект null
     * @return bool
     */
    public function is_null(): bool
    {
        return $this->is_null;
    }

    /**
     * Проверяет, содержит ли дата предмет
     * @param $subject - Предмет
     * @return bool
     */
    public function has_subject($subject): bool
    {
        return in_array((string)$subject, $this->subjects);
    }

    /**
     * Возвращает дату
     * @return DateTime
     */
    public function get_date()
    {
        return $this->date;
    }

    /**
     * Форматирует дату, по умолчанию в формате d.m. Возвращает null если объект содержит null
     * @param string $format
     * @return string|null
     */
    public function format(string $format = self::DEFAULT_FORMAT)
    {
        return $this->is_null ? null : $this->date->format($format);
    }

    /**
     * Изменяет дату по принципу \DateTime
     * @param string $interval - Интервал для изменения
     */
    public function modify(string $interval): void
    {
        if (!$this->is_null) {
            $this->date_string = null;
            $this->date->modify($interval);
            $this->update();
        }
    }

    /**
     * Проверяет, рабочий ли день содержит объект
     * @return bool
     */
    public function has_lessons(): bool
    {
        return $this->has_lessons;
    }

    /**
     * Возвращает набор предметов для этой даты
     * @return array
     */
    public function get_subjects()
    {
        return $this->subjects;
    }

    /**
     * Сравнивает даты
     * @param self $day - Второй объект \Utility\SchoolDay
     * @return bool
     */
    public function equals(self $day): bool
    {
        return !$this->is_null && !$day->is_null() && $this->format('d.m') === $day->format('d.m');
    }

    /**
     * Возвращает дату в формате '2 января'
     * @return string
     */
    public function __toString()
    {
        if($this->date_string === null)
            $this->date_string = $this->date->format('j').' '.self::MONTH_DESC[$this->date->format('n')];
        return (string)$this->date_string;
    }


    /**
     * Создаёт дату из строки в формате 'd.m'
     * @param string $date - Строка с датой
     * @return SchoolDay
     */
    public static function from(string $date): self
    {
        return self::from_format(self::DEFAULT_FORMAT, $date);
    }

    /**
     * Создаёт дату из строки в указанном формате
     * @param string $format - Строка с датой
     * @param string $date - Формат
     * @return SchoolDay
     */
    public static function from_format(string $format, string $date): self
    {

        return new self(DateTime::createFromFormat($format, $date));
    }

    /**
     * Возвращает следующий рабочий день
     * @param null|SchoolDay|DateTime $date
     * @return SchoolDay
     * @throws InvalidConfigException
     */
    public static function next_after($date = null): self
    {
        $date = ($date instanceof SchoolDay ?
                ($date->is_null()
                    ? new DateTime() :
                    $date->get_date()) :
                $date);

        $date->modify('+1 day');
        $i = 1;
        while (!Weekends::is_working($date)) {
            if($i > 366) throw new InvalidConfigException();
            $date->modify('+1 day');
            $i++;
        }
        return new self($date);
    }

    /**
     * Находит дату в данной строке
     * @param string $string - Строка с датой
     * @return SchoolDay
     * @throws InvalidMonthException
     * @throws InvalidDayException
     */
    public static function from_string(string &$string): self
    {
        $string = trim($string);

        // на 12.02
        if (preg_match(Phrases::REGEX['DATE_DAY_MONTH'], $string, $data)):
            $day = (int)$data[1];
            $month = (int)$data[2];
            if ($month < 1 || $month > 12)
                throw new InvalidMonthException(Phrases::WRONG_MONTH($month));
            if ($day < 1 || $day > cal_days_in_month(CAL_GREGORIAN, $month, date('Y')))
                throw new InvalidDayException(Phrases::WRONG_DAY($day));
            $res = self::from("$day.$month");
        // на 12 декабря
        elseif (preg_match(Phrases::REGEX['DATE_DAY_MONTH_STRING'], $string, $data)):
            $day = (int)$data[1];
            $month = self::MONTH_DESC[$data[2]];
            if ($day < 1 || $day > cal_days_in_month(CAL_GREGORIAN, $month, date('Y')))
                throw new InvalidDayException(Phrases::WRONG_DAY($day));
            $res = self::from("$day.$month");
        // на понедельник
        elseif (preg_match(Phrases::REGEX['DATE_WEEK_DAY'], $string, $data)):
            $weekday = self::WEEKDAYS_DESC[strlen($data[1]) <= 5 ?
                $data[1] : mb_substr($data[1], 0, 1) . mb_substr($data[1], 2, 1)];
            $res = self::from_format('D', $weekday);
        // на 7
        elseif (preg_match(Phrases::REGEX['DATE_DAY'], $string, $data)):
            $day = (int)$data[1];
            $month = date('m');
            $curr_day = date('d');
            $year = date('Y');
            if ($day < 1 || $day > 31)
                throw new InvalidDayException(Phrases::WRONG_DAY($day));
            if ($day < $curr_day)
                $month++;
            while ($day > cal_days_in_month(CAL_GREGORIAN, $month, $year)) {
                $month++;
            }
            $res = self::from("$day.$month");
        // на завтра
        elseif (preg_match(Phrases::REGEX['DATE_TOMORROW'], $string, $data)):
            $res = new self(new DateTime('+1 day'));
        // на сегодня
        elseif (preg_match(Phrases::REGEX['DATE_TODAY'], $string, $data)):
            $res = new self(new DateTime());
        // на вчера
        elseif (preg_match(Phrases::REGEX['DATE_YESTERDAY'], $string, $data)):
            $res = new self(new DateTime('-1 day'));
        else:
            return new self();
        endif;

        $string = trim(preg_replace("/{$data[0]}/ui", '', $string));

        return $res;
    }

}