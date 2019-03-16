<?php
namespace Utility;

use DateTime;
use const HwBot\SATURDAY_WORKING_DAY;

require_once 'Exceptions.php';

class Weekends
{

    private const VACATIONS_CONFIG_FILE = __DIR__ . '/../config/vacations.json';
    private const WEEKENDS_CONFIG_FILE = __DIR__ . '/../config/weekends.json';

    private static $vacations = [];
    private static $weekends = null;

    /**
     * Загружает даты каникул из файловой системы
     */
    private static function init_vacations()
    {
        $vacations = json_decode(file_get_contents(self::VACATIONS_CONFIG_FILE), true);
        foreach ($vacations as $name => $period) {
            self::$vacations[$name] = self::fill_period($period[0], $period[1]);
        }
    }

    public static function find_vacations_in(string $string)
    {
        if (empty(self::$vacations)) self::init_vacations();
        $regex = '';
        $first = true;
        foreach (self::$vacations as $name => $period) {
            if (!$first) $regex .= '|';
            $regex .= $name;
            $first = false;
        }
        if (preg_match("/($regex)/ui", $string, $matches)) {
            $vacations = self::$vacations[$matches[1]];
            return (['name' => $matches[1], 'start' => $vacations[0], 'end' => $vacations[count($vacations) - 1]]);
        }
        return null;
    }

    private static function fill_period(string $start, string $end)
    {
        $start = DateTime::createFromFormat('d.m', $start);
        $result = [];
        while (($temp = $start->format('d.m')) !== $end) {
            $result[] = $temp;
            $start->modify('+1 day');
        }
        $result[] = $end;
        return $result;
    }

    public static function modify_vacations($changing_name, $start, $end)
    {
        if (empty(self::$vacations)) self::init_vacations();
        if (!isset(self::$vacations[$changing_name])) return;
        $new_vacations = [];
        foreach (self::$vacations as $name => $period) {
            $new_vacations[$name] = [$period[0], $period[count($period) - 1]];
        }
        $new_vacations[$changing_name] = [$start, $end];
        file_put_contents(self::VACATIONS_CONFIG_FILE, json_encode($new_vacations, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param string $day
     * @throws InvalidWeekendException
     */
    public static function delete_weekend(string $day): void
    {
        if(empty(self::$weekends)) self::init_weekends();
        if (($key = array_search($day, self::$weekends)) !== false) {
            unset(self::$weekends[$key]);
            file_put_contents(self::WEEKENDS_CONFIG_FILE, json_encode(self::$weekends));
        } else throw new InvalidWeekendException();
    }

    /**
     * @param string $day
     * @throws InvalidWeekendException
     */
    public static function add_weekend(string $day): void
    {
        if(!in_array($day, self::$weekends)) {
            self::$weekends[] = $day;
            file_put_contents(self::WEEKENDS_CONFIG_FILE, json_encode(self::$weekends));
        } else throw new InvalidWeekendException();
    }

    /**
     * Загружает даты выходных из файловой системы
     */
    private static function init_weekends()
    {
        self::$weekends = json_decode(file_get_contents(self::WEEKENDS_CONFIG_FILE), true);
    }

    /**
     * Возвращает название и сроки каникул, которые содержат данный день. Null если день рабочий
     * @param SchoolDay|string $day - Дата
     * @return array|null
     */
    public static function get_vacation_for($day)
    {
        if ($day instanceof SchoolDay) $day = $day->format();
        if (empty(self::$vacations)) self::init_vacations();
        foreach (self::$vacations as $name => $period) {
            if (in_array($day, $period)) return ['name' => $name, 'start' => $period[0], 'end' => $period[count($period) - 1]];
        }
        return null;
    }

    /**
     * Проверяет, рабочий ли данный день
     * @param SchoolDay|DateTime $day - Дата
     * @return bool
     */
    public static function is_working($day)
    {
        if ($day instanceof SchoolDay) $day = $day->get_date();
        if (self::$weekends === null) self::init_weekends();
        if (empty(self::$vacations)) self::init_vacations();
        $week_day = $day->format('D');
        $date = $day->format('d.m');
        return !empty(SCHEDULE[$week_day]) && !in_array($date, self::$weekends) && self::get_vacation_for($date) === null;
    }

    /**
     * Находит первые каникулы после указанного дня
     * @param DateTime|null $date - Дата
     * @return array|null
     * @throws InvalidConfigException
     */
    public static function get_vacation_after(DateTime $date = null)
    {
        if ($date === null) $date = new DateTime();
        else $date = clone $date;
        $i = 0;
        while (($temp = self::get_vacation_for($date->format('d.m'))) === null) {
            if ($i >= 365) throw new InvalidConfigException();
            $date->modify('+1 day');
            $i++;
        }
        return $temp;
    }

}