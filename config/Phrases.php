<?php

namespace HwBot;

use HwBot\Parser\Parser;
use Utility\Subject;
use Utility\Utility;

class Phrases {
    const EXPECTED_ANSWERS = [
        'POSITIVE' => ['да', 'ес', 'yes', 'ок', 'ok'],
        'NEGATIVE' => ['нет', 'no', 'ноу', 'не'],
    ];
    const PREDEFINED = [
        '/(пр[ие]в|з?дра|хел+о|хей)/' => [
            'Привет', 'Здравствуй', 'Добрый день', 'Хелло',
        ],
        '/(т[иы]\s+)?[хк]то(?(1)|\s+т[иы])/' => [
            'Я Коннор, прислан из Киберлайф',
            'Я Маркус, но друзья зовут меня устраивать реловюцию',
            'Я?',
        ],


        '//' => [
            'Не понел', 'Что?', 'А?', 'Сложнааа',
        ]
    ];
    const REGEX = [
        'ADD_WEEKEND' => '/^добав\p{L}*\s+выходн/ui',
        'REMOVE_WEEKEND' => '/^удал\p{L}*\s+выходн/ui',
        'ADD_HOMEWORK' => '/^добав/ui',
        'GET_HOMEWORK' => '/^[чш]т?[оёе]\s*(по|на|зад)/ui',
        'REMOVE_HOMEWORK' => '/^удал/ui',
        'CHECK_VACATIONS' => '/^(к[оа]гда|сколь\p{L}*\s+д\p{L}+)\s+к[уа]ник/ui',
        'CHANGE_VACATIONS' => '/^измен\p{L}*\s+к[ауо]ник/ui',

        'DATE_DAY_MONTH' => '/(?:на|с|по|до)\s*(\d{1,2})[^\p{L}](\d{1,2})/ui',
        'DATE_DAY_MONTH_STRING' => '/(?:на|с|по|до)\s*(\d{1,2})\s*(янв|фев|мар|апр|мая|ию[нл]|авг|сен|окт|ноя|дек)\p{L}*/ui',
        'DATE_WEEK_DAY' => '/(?:на|с|по|до)\s+(по?н|вт|ср|че?т|пя?т|су?б|во?с)\p{L}*/ui',
        'DATE_DAY' => '/(?:на|с|по|до)\s*(\d{1,2})/ui',
        'DATE_TOMORROW' => '/(?:на|с|по|до)\s+завтр\p{L}*/ui',
        'DATE_TODAY' => '/(?:на|с|по|до)\s+сегод\p{L}*/ui',
        'DATE_YESTERDAY' => '/(?:на|с|по|до)\s+вчер\p{L}*/ui',
    ];

    const ERROR = "Что-то пошло не такк :(";
    const CANCEL = "Ок, отмена";

    /* SchoolDay::from_string */
    const VACATIONS_NOT_FOUND = 'Я не нашёль каникулы';
    const DATE_NOT_FOUND = 'Я не нашёль дату';
    public static function WRONG_MONTH($m) {return "Неверный месяц '$m'";}
    public static function WRONG_DAY($d) {return "Неверный день '$d'";}

    /* Bot::change_vacations */
    const WEEKENDS_CHANGED = 'Каникулы изменены';
    public static function CHANGE_WEEKENDS_CONFIRM($p, $fd, $sd) {return "Изменить $p аникулы?\n".
        "Новый период: с $fd по $sd\n";}

    /* Bot::add_weekend */
    public static function WEEKEND_ADDED($w) {return "Добавлен выходной на $w";}
    public static function WEEKEND_DUPLICATE($w) {return "$w уже отмечен как выходной";}

    /* Bot::delete_weekend */
    public static function WEEKEND_REMOVED($w) {return "Удалён выходной с $w";}
    public static function WEEKEND_MISSING($w) {return "$w - это не выходной";}

    /* Bot::get_vacations */
    const VACATIONS_NOW = 'Сейчас уже каникулы :3';
    const VACATIONS_WRONG_CONFIG = 'Я не смог найти каникулы: что-то неправильно настроено';
    public static function VACATIONS_DATA($p, $start, $finish, $d) {
        $msg = "Следующие каникулы - $p: с $start до $finish\n";
        $msg .= "До них осталось $d ".Parser::decline($d, '', ['день', 'дня', 'дней']);
        $w = ceil($d / 7);
        if($w >= 1) {
            $msg .= " (".($w === $d/7 ?
                    "$w ".Parser::decline($w, 'недел', ['я', 'и', 'ь']) :
                    "меньше $w ".Parser::decline($w, 'недел', ['и', 'ь', 'ь']).")");
        }
        return $msg;
    }

    /* Bot::get_homework */
    const DAY_NOT_WORKING = 'Это не рабочий день';
    const WEEKENDS_WRONG_CONFIG = 'Я не смог достать задание: что-то неправильно настроено';
    public static function HOMEWORK_DATA($explicit, $for_day, $hw, $hwm, $base) {
        $msg = 'Вот, что я нашёль';
        // Date should be added here in 2 cases:
        // 1. User asks hw for day, but doesn't explicitly say what day
        // 2. User asks for special subjects and specifies day
        if($for_day xor $explicit)
            $msg .= " на $base";
        $msg .= "\n";

        $add_date = !$for_day && !$explicit;
        $should_alert = !$for_day && $explicit;
        foreach($hw as $data) {
            $subject = $data['bundle']->get_subject();
            $date = $data['bundle']->get_day();
            if($should_alert && !$data['on_day']) {
                $msg .= "• $base нет {$subject->get_genitive()}. После этого будет $date:";
            }
            else {
                $msg .= "• По {$subject->get_dative()}";
                if ($add_date) {
                    $msg .= " на " . $date;
                }
                $msg .= ':';
            }
            $msg .= "\n― ".Utility::mb_ucfirst($data['bundle']->get_homework())."\n";
        }
        foreach ($hwm as $data) {
            $subject = $data['bundle']->get_subject();
            $date = $data['bundle']->get_day();
            if($should_alert && !$data['on_day']) {
                $msg .= "• $base нет {$subject->get_genitive()}. После этого будет $date:";
            }
            else {
                $msg .= "• По {$subject->get_dative()}";
                if ($add_date) {
                    $msg .= " на " . $date;
                }
                $msg .= ':';
            }
            $msg .= "\n";
        }
        if(!empty($hwm)) $msg .= '― Нет в базе';

        return $msg;
    }

    /* Bot::set_homework */
    const HOMEWORK_NOT_FOUND = 'Я не нашёль задание';
    const SUBJECT_NOT_FOUND = 'Я не нашёль предмет';
    public static function NOT_IN_SHEDULE($s) {return Utility::mb_ucfirst($s)." нет в расписании";}
    public static function SET_HW_CONFIRM_DATE_RIGHT($date, $subject, $hw, $existing) {
        $msg = '';
        if($existing === null)
            $msg .= "Записать на $date по {$subject->get_dative()} следующее?\n";
        else
            $msg .= "На $date уже записано задание по {$subject->get_dative()}:\n$existing\n".
                "Заменить на следующее?\n";
        return $msg . $hw;
    }
    public static function SET_HW_CONFIRM_DATE_WRONG($date, $next_date, $subject, $hw, $existing) {
        $msg = "$date нет {$subject->get_genitive()}. После этого будет $next_date. ";
        if($existing === null)
            $msg .= "Записать на этот день следующее?\n";
        else
            $msg .= "На этот день уже записано задание:\n$existing\nЗаменить на следующее?\n";
        return $msg . $hw;
    }
    const HW_ADDED = "Добавлено в БД";

    /* Bot::delete_homework */
    public static function SUBJECT_NOT_ON_DATE(Subject $s, $d) {return "$d нет {$s->get_genitive()}";}
    public static function NO_HOMEWORK_SAVED($s, $d) {return "На $d не записано задание по {$s->get_dative()}";}
    public static function DELETE_HOMEWORK_CONFIRM($s, $d, $hw) {
        return "На $d записано задание по {$s->get_dative()}:\n$hw\nУдалить?";
    }
    const DELETED = 'Удалено';
}
