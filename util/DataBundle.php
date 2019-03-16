<?php
namespace Utility;

require_once "Exceptions.php";
class DataBundle {

    private $locked = false;
    private $homework = null;
    private $day = null;
    private $subjects = [];
    private $subject = null;

    public function lock() : self {
        $this->locked = true;
        return $this;
    }

    /**
     * @param string $homework
     * @return DataBundle
     */
    public function set_homework(string $homework): self {
        if(!$this->locked) $this->homework = trim($homework);
        return $this;
    }

    /**
     * @param SchoolDay $date
     * @return DataBundle
     */
    public function set_day(SchoolDay $date): self {
        if(!$this->locked) $this->day = $date;
        return $this;
    }

    /**
     * @param Subject|array $subject
     * @return DataBundle
     */
    public function set_subject($subject): self {
        if(!$this->locked) {
            if(is_array($subject)) {
                $this->subjects = $subject;
                $this->subject = $subject[0];
            } else {
                $this->subjects = (string)$subject === Subject::NONE ? [] : [$subject];
                $this->subject = $subject;
            }
        }
        return $this;
    }

    /**
     * @return null|string
     */
    public function get_homework() {
        return $this->homework;
    }

    /**
     * @return SchoolDay
     */
    public function get_day() : SchoolDay {
        return $this->day;
    }

    /**
     * @return array
     */
    public function get_subjects() : array {
        return $this->subjects;
    }

    /**
     * @return Subject
     */
    public function get_subject() : Subject {
        return $this->subject;
    }

    public function __toString() : string {
        $result =
            "Date: ".($this->day instanceof SchoolDay ? $this->day->format() : 'null')."\nSubject: ";
        foreach($this->subjects as $subject)
            $result .= $subject.", ";
        $result = preg_replace('/,\s$/', '', $result);
        $result .= "\nHomework: ".($this->homework ?? 'null')."\n";
        return $result;
    }


}