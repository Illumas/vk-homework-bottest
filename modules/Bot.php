<?php

namespace HwBot;

use DateTime;
use HwBot\VkApi\VkApi as Vk;
use HwBot\Parser\Parser;
use HwBot\VkApi\VkApi;
use InvalidArgumentException;
use Utility\BotWorkException;
use Utility\DataBundle;
use Utility\InvalidConfigException;
use Utility\InvalidDateException;
use Utility\InvalidWeekendException;
use Utility\MissingDataException;
use Utility\NoDateException;
use Utility\NoHomeworkException;
use Utility\NoSubjectException;
use const Utility\SCHEDULE;
use Utility\SchoolDay;
use Utility\Subject;
use Utility\SubjectNotOnDateException;
use Utility\Utility;
use Utility\Weekends;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../util/Exceptions.php';
require_once __DIR__ . '/../util/Weekends.php';
require_once __DIR__ . '/../util/Utility.php';
require_once __DIR__ . '/VkApi.php';
require_once __DIR__ . '/Parser.php';

class Bot
{
    protected $peer_id;
    protected $from_id;
    protected $message_id;
    protected $is_conv;
    protected $debug_mode = false;

    protected $temp_data = [];

    public function __construct($object)
    {
        $this->peer_id = $object['peer_id'];
        $this->from_id = $object['from_id'];
        $this->message_id = $object['id'];
        $this->is_conv = $this->peer_id !== $this->from_id;

        register_shutdown_function(function () {
            $last_error = error_get_last();
            if ($last_error !== null) {
                Utility::log_err($last_error['message']);
                if(DEV_MODE) {
                    print_r($last_error);
                }
                if ($this->debug_mode) {
                    $this->send_message($last_error['message']);
                }
            }
        });
    }

    public function handle_event($event)
    {
        switch ($event['type']):
            case EVENT_CONFIRMATION:
                exit(CALLBACK_API_CONFIRMATION_TOKEN);
            case EVENT_MESSAGE_NEW:
                $this->handle_new_message($event['object']);
        endswitch;
    }

    protected function handle_new_message($object)
    {
        // Check saved temporary data
        $temp_file_name = __DIR__ . "/../temp/message_{$this->peer_id}-{$this->from_id}.json";
        $file_exists = file_exists($temp_file_name);
        $temp_data = $file_exists ?
            json_decode(file_get_contents($temp_file_name), true) : [];

        // Delete leading response text
        $text = preg_replace('/^.*?\[club.+?\][\s,]*/uim', '', $object['text']);
        // Check for debug mode
        if (preg_match('/^--debug/ui', $text)) {
            $text = preg_replace('/^--debug\s*/ui', '', $text);
            $this->debug_mode = true;
        }
        $text = trim($text);

        $msg = '';

        if ($this->debug_mode && isset($temp_data['func']))
            $msg .= "Temp data function: {$temp_data['func']}\n";

        // Delete messages if requested
        if (isset($temp_data['delete_msg'])) {
            VkApi::delete_message($temp_data['delete_msg']);
            unset($temp_data['delete_msg']);
        }

        // Handle previous request
        if (isset($temp_data['func']) && isset($temp_data['expected']) &&
            in_array(mb_strtolower($text), $temp_data['expected'])):
            $msg = call_user_func([$this, $temp_data['func']], $text, $temp_data);
        elseif (preg_match(Phrases::REGEX['ADD_WEEKEND'], $text)):
            $msg .= $this->add_weekend($text);
        elseif (preg_match(Phrases::REGEX['REMOVE_WEEKEND'], $text)):
            $msg .= $this->delete_weekend($text);
        elseif (preg_match(Phrases::REGEX['ADD_HOMEWORK'], $text)): // Add homework
            $msg .= $this->set_homework($text);
        elseif (preg_match(Phrases::REGEX['GET_HOMEWORK'], $text)): // Get homework
            $msg .= $this->get_homework($text);
        elseif (preg_match(Phrases::REGEX['REMOVE_HOMEWORK'], $text)): // Delete homework
            $msg .= $this->delete_homework($text);
        elseif (preg_match(Phrases::REGEX['CHECK_VACATIONS'], $text)): // Check vacations
            $msg .= $this->get_vacations();
        elseif (preg_match(Phrases::REGEX['CHANGE_VACATIONS'], $text)):
            $msg .= $this->change_vacations($text);
        else:
            foreach (Phrases::PREDEFINED as $regex => $answers) if (preg_match($regex . 'ui', $text)) {
                $msg .= $answers[mt_rand(0, count($answers) - 1)];
                break;
            }
        endif;

        if(DEV_MODE) echo $msg."\n\n";
        else if (trim($msg) !== '') $this->send_message($msg);

        // Save/clear temp data
        if(!empty($this->temp_data))
            file_put_contents($temp_file_name, json_encode(array_merge($this->temp_data, [
                'time' => time(),
            ]), JSON_UNESCAPED_UNICODE));
        else if($file_exists)
            unlink($temp_file_name);

    }

    private function change_vacations($text, $last_request = []): string
    {
        $msg = '';
        if(!isset($last_request['status'])):
            $period = Weekends::find_vacations_in($text);

            if ($period === null) return $msg . Phrases::VACATIONS_NOT_FOUND;
            if ($this->debug_mode) $msg .= "Found: {$period['name']}\n";

            try {
                $first_date = SchoolDay::from_string($text);
                $second_date = SchoolDay::from_string($text);
            } catch (InvalidDateException $e) {
                return $msg . $e->getMessage();
            }
            if ($first_date->is_null()) return $msg . Phrases::DATE_NOT_FOUND;

            $msg .= Phrases::CHANGE_WEEKENDS_CONFIRM($period['name'], $first_date,
                $second_date->is_null() ? SchoolDay::from($period['end']) : $second_date);

            $this->temp_data = [
                'func' => __FUNCTION__,
                'status' => 'acceptation',
                'expected' => array_merge(Phrases::EXPECTED_ANSWERS['POSITIVE'], Phrases::EXPECTED_ANSWERS['NEGATIVE']),
                'name' => $period['name'],
                'start' => $first_date->format(),
                'end' => $second_date->is_null() ? $period['end'] : $second_date->format(),
            ];
        elseif($last_request['status'] === 'acceptation'):
            Weekends::modify_vacations($last_request['name'], $last_request['start'], $last_request['end']);
            $msg .= Phrases::WEEKENDS_CHANGED;
        endif;

        return $msg;
    }

    private function add_weekend($text): string
    {
        $msg = '';
        try {
            $day = SchoolDay::from_string($text);
            if($day->is_null()) throw new NoDateException(Phrases::DATE_NOT_FOUND);
            if($this->debug_mode) $msg .= "Date found: {$day->format()}\n";
            Weekends::add_weekend($day->format());
            $msg .= Phrases::WEEKEND_ADDED($day);
        } catch(NoDateException $e) {
            $msg .= $e->getMessage();
        } catch (InvalidDateException $e) {
            $msg .= $e->getMessage();
        } catch(InvalidWeekendException $e) {
            $msg .= Phrases::WEEKEND_DUPLICATE($day);
        }

        return $msg;
    }

    private function delete_weekend($text): string
    {
        $msg = '';
        try {
            $day = SchoolDay::from_string($text);
            if($this->debug_mode) $msg .= "Date found: ".$day->format()."\n";
            Weekends::delete_weekend($day->format());
            $msg .= Phrases::WEEKEND_REMOVED($day);
        } catch (InvalidDateException $e) {
            $msg .= ($this->debug_mode ? $e : $e->getMessage());
        } catch (InvalidWeekendException $e) {
            $msg .= Phrases::WEEKEND_MISSING($day);
        }

        return $msg;
    }

    private function get_vacations()
    {
        $msg = '';
        $temp = Weekends::get_vacation_for(date('d.m'));
        if ($temp !== null) {
            $msg .= Phrases::VACATIONS_NOW . "\n";
            $from_date = DateTime::createFromFormat('d.m.Y', $temp['end']);
            $from_date->modify('+1 day');
        } else {
            $from_date = new DateTime();
        }

        try {
            $next_vacations = Weekends::get_vacation_after($from_date);
        } catch (InvalidConfigException $e) {
            return $msg . Phrases::VACATIONS_WRONG_CONFIG;
        }

        $interval = (new DateTime())->diff(DateTime::createFromFormat('d.m', $next_vacations['start']));

        $msg .= Phrases::VACATIONS_DATA(
            /* Name  */ $next_vacations['name'],
            /* Start */ $next_vacations['start'],
            /* End   */ $next_vacations['end'],
            /* Days  */ $interval->format('%m') === '0' ?
                $interval->format('%d') : $interval->format('%a')
        );
        return $msg;
    }

    private function get_homework($text): string
    {
        // Very funny joke, remove if not needed
        if (mt_rand(0, 512) === 0) {
            $this->temp_data = ['delete_msg' => '' . $this->send_message('нет')];
            return '';
        }
        $msg = '';

        try {
            $bundle = Parser::parse($text);
            $subjects = $bundle->get_subjects();
            $date = $bundle->get_day();
            $date_explicit = !$date->is_null();
            $base_date = $date_explicit ? (string)$date : '';
            $for_day = empty($subjects);
            $has_lessons = $date->has_lessons();

            if ($this->debug_mode)
                $msg .= $bundle;

            if (!$has_lessons) {
                if ($date_explicit)
                    $msg .= Phrases::DAY_NOT_WORKING . "\n";

                try {
                    $date = SchoolDay::next_after($date);
                    $date_explicit = false;
                    if($base_date === '') $base_date = (string)$date;
                } catch(InvalidConfigException $e) {
                    return $msg . Phrases::WEEKENDS_WRONG_CONFIG;
                }
            }
            if ($for_day) $subjects = SCHEDULE[$date->format('D')];

            $hw = []; $hw_missing = [];
            $conn = Utility::dbConnect();
            $stmt = $conn->prepare("SELECT homework FROM " . DB_HW_TABLE .
                " WHERE subject = :subject AND date = :date");
            foreach ($subjects as $subject) {
                if (!($subject instanceof Subject)) $subject = new Subject($subject);
                $sp_date = $for_day ? $date : $subject->get_next_day_from($date);
                $bundle = (new DataBundle())
                    ->set_subject($subject)
                    ->set_day($sp_date);

                $stmt->execute([':subject' => (string)$subject, ':date' => $sp_date->format()]);
                $data = $stmt->fetch();
                if ($this->debug_mode) $msg .= "$subject, {$sp_date->format()}, db: " . print_r($data, true) . "\n";
                empty($data) ?
                    $hw_missing[] = [
                        'bundle' => $bundle->lock(),
                        'on_day' => $sp_date !== null && $base_date === (string)$sp_date,
                    ] :
                    $hw[] = [
                        'bundle' => $bundle
                            ->set_homework($data['homework'])
                            ->lock(),
                        'on_day' => $sp_date !== null && $base_date === (string)$sp_date,
                    ];
            }
            $conn = $stmt = null;
            $msg .= Phrases::HOMEWORK_DATA(
                $date_explicit, $for_day,
                $hw, $hw_missing, $base_date
            );
        } catch (InvalidDateException $e) {
            $msg .= $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $msg .= $e->getMessage();
        } catch (\Exception $e) {
            Utility::log_err($e);
            $msg .= $this->debug_mode ? (string)$e : Phrases::ERROR;
        }
        return $msg;
    }

    private function set_homework($text, $last_demand = []): string
    {
        $text = preg_replace('/^добав\p{L}*\s*/ui', '', $text);
        $msg = '';
        // First demand
        if (!isset($last_demand['status'])) try {
            $bundle = Parser::parse($text);
            $date = $bundle->get_day();
            $subject = $bundle->get_subject();
            $homework = Utility::mb_ucfirst($bundle->get_homework());
            $next_day = $subject->get_next_day_from($date);

            if ($date->is_null()) $date = $next_day;
            if ((string)$subject === Subject::NONE) throw new NoSubjectException(Phrases::SUBJECT_NOT_FOUND);
            if ($homework === '') throw new NoHomeworkException(Phrases::HOMEWORK_NOT_FOUND);

            $conn = Utility::dbConnect();
            $date_right = $date->has_subject($subject);

            if ($date_right) {
                $existing = $conn
                    ->query("SELECT homework FROM " . DB_HW_TABLE .
                                      " WHERE subject = '$subject' AND date = '{$date->format()}'")
                    ->fetch();
                $msg .= Phrases::SET_HW_CONFIRM_DATE_RIGHT($date, $subject, $homework, $existing['homework'] ?? null);
            } else if ($next_day === null)
                return Phrases::NOT_IN_SHEDULE($subject);
            else {
                $existing = $conn
                    ->query("SELECT homework FROM " . DB_HW_TABLE .
                                      " WHERE subject = '$subject' AND date = '{$next_day->format()}'")
                    ->fetch();
                $msg .= Phrases::SET_HW_CONFIRM_DATE_WRONG($date, $next_day,
                    $subject, $homework, $existing['homework'] ?? null);
            }
            $conn = null;
            if ($this->debug_mode)
                $msg .= "\n" . $bundle . "Next day: $next_day\nStatus: acceptation\n";

            $this->temp_data = [
                'func' => __FUNCTION__,
                'status' => 'acceptation',
                'date' => $date_right ? $date->format() : $next_day->format(),
                'homework' => $homework,
                'subject' => (string)$subject,
                'expected' => array_merge(Phrases::EXPECTED_ANSWERS['POSITIVE'], Phrases::EXPECTED_ANSWERS['NEGATIVE']),
                'replace' => !empty($existing),
            ];

        } catch (BotWorkException $e) {
            $msg .= $e->getMessage();
        } catch (\Exception $e) {
            Utility::log_err($e);
            $msg .= $this->debug_mode ? (string)$e : Phrases::ERROR;
        }
        // If needed acceptation or refusal
        else if ($last_demand['status'] === 'acceptation') {
            if (!in_array(mb_strtolower($text), Phrases::EXPECTED_ANSWERS['POSITIVE']))
                return Phrases::CANCEL;

            $query = $last_demand['replace'] ?
                "UPDATE " . DB_HW_TABLE . " SET homework = '{$last_demand['homework']}' " .
                "WHERE subject = '{$last_demand['subject']}' AND date = '{$last_demand['date']}'" :

                "INSERT INTO " . DB_HW_TABLE . " (date, subject, homework) " .
                "VALUES ('{$last_demand['date']}', '{$last_demand['subject']}', '{$last_demand['homework']}')";

            if ($this->debug_mode) $msg .= "Homework: {$last_demand['homework']}\nDate: {$last_demand['date']}\nSubject: {$last_demand['subject']}\nQuery: $query\n";
            $conn = Utility::dbConnect();
            $conn->query($query);
            if ($this->debug_mode)
                $msg .= "Date: {$last_demand['date']}\nSubject: {$last_demand['subject']}\nHomework: {$last_demand['homework']}\n" .
                    "Replacing: " . ($last_demand['replace'] ? 'true' : 'false') . "\nSuccess; Temp empt\n";

            $msg .= Phrases::HW_ADDED;
        }
        return $msg;
    }

    private function delete_homework($text, $last_demand = []): string
    {
        $msg = '';
        if (!isset($last_demand['status'])) try {
            $bundle = Parser::parse($text);
            $date = $bundle->get_day();
            $subject = $bundle->get_subject();

            if ($date->is_null())
                throw new NoDateException(Phrases::DATE_NOT_FOUND);
            if ((string)$subject === Subject::NONE)
                throw new NoSubjectException(Phrases::SUBJECT_NOT_FOUND);
            if (!$date->has_subject($subject))
                throw new SubjectNotOnDateException(Phrases::SUBJECT_NOT_ON_DATE($subject, $date));

            $conn = Utility::dbConnect();
            $data = $conn
                ->query("SELECT homework FROM " . DB_HW_TABLE . " WHERE subject = '$subject' AND date = '{$date->format()}'")
                ->fetch();
            $conn = null;
            if (empty($data))
                $msg .= Phrases::NO_HOMEWORK_SAVED($subject, $date);
            else {
                $msg .= Phrases::DELETE_HOMEWORK_CONFIRM($subject, $date, $data['homework']);
                $this->temp_data = [
                    'func' => __FUNCTION__,
                    'status' => 'acceptation',
                    'expected' => array_merge(Phrases::EXPECTED_ANSWERS['POSITIVE'], Phrases::EXPECTED_ANSWERS['NEGATIVE']),
                    'date' => $date->format(),
                    'subject' => (string)$subject,
                ];
            }
            if ($this->debug_mode)
                $msg .= "\n" . $bundle . "Query: " . print_r($data, true) . "\n";

        } catch (InvalidDateException $e) {
            $msg .= $e->getMessage();
        } catch (MissingDataException $e) {
            $msg .= $e->getMessage();
        } catch (SubjectNotOnDateException $e) {
            $msg .= $e->getMessage();
        } catch (\Exception $e) {
            Utility::log_err($e);
            $msg .= $this->debug_mode ? (string)$e : Phrases::ERROR;
        }
        else if ($last_demand['status'] === 'acceptation') {
            if ($this->debug_mode)
                $msg .= "Status: acceptation\n";
            if (!in_array(mb_strtolower($text), Phrases::EXPECTED_ANSWERS['NEGATIVE']))
                return Phrases::CANCEL;

            $conn = Utility::dbConnect();
            $conn->query("DELETE FROM " . DB_HW_TABLE .
                " WHERE subject = '{$last_demand['subject']}' AND date = '{$last_demand['date']}'");
            if ($this->debug_mode)
                $msg .= "Success; temp empty\n";
            $msg .= Phrases::DELETED;
        }
        return $msg;
    }

    protected function send_message($msg)
    {
        return Vk::send_message($this->peer_id, $msg, $this->is_conv ? $this->message_id : '');
    }
}