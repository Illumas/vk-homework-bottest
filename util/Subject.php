<?php
namespace Utility;


use DateTime;
use Exception;
use InvalidArgumentException;

require_once 'SchoolDay.php';

define(__NAMESPACE__.'\SCHEDULE',
    json_decode(file_get_contents(__DIR__.'/../config/timeTable.json'), true));

class Subject {

    public const NONE          = '';
    public const ALGEBRA       = 'алгебра';
    public const GEOMETRY      = 'геометрия';
    public const ENGLISH_F     = 'английский 1гр';
    public const ENGLISH_S     = 'английский 2гр';
    public const BIOLOGY       = 'биология';
    public const HISTORY       = 'история';
    public const CHINESE_F     = 'китайский 1гр';
    public const CHINESE_S     = 'китайский 2гр';
    public const LITERATURE    = 'литература';
    public const SOC_KNOWLEDGE = 'обществознание';
    public const RUSSIAN_F     = 'русский 1гр';
    public const RUSSIAN_S     = 'русский 2гр';
    public const PHYSICS       = 'физика';
    public const CHEMISTRY     = 'химия';

    private const REGEX = "алг|геом|мат|[аи]нг|био|ист|кит|лит|общ|рус|физ|хим";

    public const DESC = [
        self::ALGEBRA => [
            'dat' => 'алгебре',
            'gen' => 'алгебры',
            'regex' => 'алг|мат',
        ],
        self::GEOMETRY => [
            'dat' => 'геометрии',
            'gen' => 'геометрии',
            'regex' => 'геом|мат',
        ],
        self::ENGLISH_F => [
            'dat' => 'английскому у 1гр.',
            'gen' => 'английского у 1гр.',
            'regex' => '[аи]нг',
            'divided' => true,
        ],
        self::ENGLISH_S => [
            'dat' => 'английскому у 2гр.',
            'gen' => 'английского у 2гр.',
            'regex' => '[аи]нг',
            'divided' => true,
        ],
        self::BIOLOGY => [
            'dat' => 'биологии',
            'gen' => 'биологии',
            'regex' => 'био',
        ],
        self::HISTORY => [
            'dat' => 'истории',
            'gen' => 'истории',
            'regex' => 'ист',
        ],
        self::CHINESE_F => [
            'dat' => 'китайскому у 1гр.',
            'gen' => 'китайского у 1гр.',
            'regex' => 'кит',
            'divided' => true,
        ],
        self::CHINESE_S => [
            'dat' => 'китайскому у 2гр.',
            'gen' => 'китайского у 2гр.',
            'regex' => 'кит',
            'divided' => true,
        ],
        self::LITERATURE => [
            'dat' => 'литературе',
            'gen' => 'литературы',
            'regex' => 'лит',
        ],
        self::SOC_KNOWLEDGE => [
            'dat' => 'обществознанию',
            'gen' => 'обществознания',
            'regex' => 'общ',
        ],
        self::RUSSIAN_F => [
            'dat' => 'русскому у 1гр.',
            'gen' => 'русского у 1гр.',
            'regex' => 'рус',
            'divided' => true,
        ],
        self::RUSSIAN_S => [
            'dat' => 'русскому у 2гр.',
            'gen' => 'русского у 2гр.',
            'regex' => 'рус',
            'divided' => true,
        ],
        self::PHYSICS => [
            'dat' => 'физике',
            'gen' => 'физики',
            'regex' => 'физ',
        ],
        self::CHEMISTRY => [
            'dat' => 'химии',
            'gen' => 'химии',
            'regex' => 'хим',
        ],
    ];

    private $subject;
    private $week_days;

    /**
     * @param string $subject
     * @throws Exception
     */
    public function __construct(string $subject = self::NONE) {
        if($subject !== self::NONE && !array_key_exists($subject, self::DESC))
            throw new InvalidArgumentException("Subject $subject doesn't exist");

        $this->subject = $subject;
        $this->week_days = array_keys(array_filter(SCHEDULE, function($schedule) use($subject) {
            return in_array($subject, $schedule);
        }));
    }

    /**
     * Находит следующий рабочий день после данного
     * @param SchoolDay|null $day - Дата
     * @return SchoolDay
     * @throws Exception
     */
    public function get_next_day_from(SchoolDay $day = null) : SchoolDay {
        if($day === null || $day->is_null()) $day = new SchoolDay(new DateTime('+1 day'));
        $init_weekday = $day->format('D');
        $next_day = new SchoolDay(clone $day->get_date());
        while(!$next_day->has_subject($this->subject)) {
            $next_day->modify('+1 day');
            if($next_day->format('D') === $init_weekday) return new SchoolDay();
        }
        return $next_day;
    }

    /**
     * Проверяет, есть ли предмет в данной дате
     * @param SchoolDay $day - Дата
     * @return bool
     */
    public function is_on(SchoolDay $day) : bool {
        return $day->has_subject($this->subject);
    }

    /**
     * Возвращает строку с предметов в именительном падеже
     * @return string
     */
    public function __toString() {
        return $this->subject;
    }

    /**
     * Возвращает строку с предметом в дательном падеже
     * @return string|null
     */
    public function get_dative() {
        return is_string($this->subject) ? self::DESC[$this->subject]['dat'] : null;
    }

    /**
     * Возвращает строку с предметом в родительном падеже
     * @return string|null
     */
    public function get_genitive() {
        return self::DESC[$this->subject]['gen'] ?? null;
    }

    /**
     * Возвращает строку с предметом в дательном падеже
     * @param string|Subject $subj - Предмет
     * @return string|null
     */
    public static function get_dative_of($subj) : string {
        if($subj instanceof self) return $subj->get_dative();
        return isset(self::DESC[$subj]) && isset(self::DESC[$subj]['dat']) ? self::DESC[$subj]['dat'] : null;
    }

    /**
     * Возвращает строку с предметом в родительном падеже
     * @param string|Subject $subj - Предмет
     * @return string|null
     */
    public static function get_genitive_of(string $subj) : string {
        if($subj instanceof self) return $subj->get_genitive();
        return isset(self::DESC[$subj]) && isset(self::DESC[$subj]['gen']) ? self::DESC[$subj]['gen'] : null;
    }



    /**
     * Находит дату в данной строке
     * @param string $string
     * @return Subject|array
     * @throws Exception
     */
    public static function from_string(string &$string) {
        preg_match('/(?:\p{L}{1,3}\s+)?('.self::REGEX.')\p{L}*(?:\s*([12])[гр]+)?/ui', trim($string), $data);
        if(empty($data)) return new self();

        $string = preg_replace("/{$data[0]}/ui", '', $string);
        $subjects = array_keys(array_filter(self::DESC, function($val) use ($data) {
            preg_match('/'.$val['regex'].'/ui', $data[0], $result);
            return !empty($result);
        }));

        $len = count($subjects);
        if($len > 2)
            throw new Exception("More than 2 subjects aren't possible");

        if($len == 2) {
            if((self::DESC[$subjects[0]]['divided'] ?? false) && isset($data[2])) {
                return new self($subjects[$data[2] - 1]);
            }
            return [new self($subjects[0]), new self($subjects[1])];
        }

        return new self($subjects[0]);
    }

}