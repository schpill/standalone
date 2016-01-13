<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    /*
        $period = lib("period")->createFromDuration('2014-10-03 08:12:37', 3600);
        $start = $period->getStartDate(); //return the following DateTime: DateTime('2014-10-03 08:12:37');
        $end   = $period->getEndDate(); //return the following DateTime: DateTime('2014-10-03 09:12:37');
        $duration  = $period->getDateInterval(); //return a DateInterval object
        $duration2 = $period->getTimestampInterval(); //return the same interval expressed in seconds.
        echo $period; //displays '2014-10-03T08:12:37Z/014-10-03T09:12:37Z'


        $period = lib("period")->createFromMonth(2014, 10);
        foreach ($period->getDatePeriod('1 DAY') as $day) {
            $day->format('Y-m-d');
        }

        $period    = lib("period")->createFromDuration('2014-01-01', '1 WEEK');
        $altPeriod = lib("period")->createFromWeek(2014, 3);
        $period->sameDurationAs($altPeriod); //will return true because the duration are equals
        $period->sameValueAs($altPeriod); //will return false because the endpoints differ

        $period    = lib("period")->createFromDuration('2014-01-01', '1 WEEK');
        $altPeriod = $period->endingOn('2014-02-03');
        $period->contains($altPeriod); //return false;
        $altPeriod->durationGreaterThan($period); //retu
    */

    namespace Thin;

    use DateInterval;
    use DateTime;
    use DateTimeImmutable;
    use DateTimeInterface;
    use DateTimeZone;
    use InvalidArgumentException;
    use JsonSerializable;
    use LogicException;
    use OutOfRangeException;

    class PeriodLib implements JsonSerializable
    {
        /**
         * Date Format to create ISO8601 Interval format
         */
        const DATE_ISO8601 = 'Y-m-d\TH:i:s.u\Z';

        /**
         * Date Format for timezoneless DateTimeInterface
         */
        const DATE_LOCALE = 'Y-m-d H:i:s.u';

        /**
         * PeriodLib starting included datepoint.
         *
         * @var \DateTimeImmutable
         */
        protected $startDate;

        /**
         * PeriodLib ending excluded datepoint.
         *
         * @var \DateTimeImmutable
         */
        protected $endDate;

        /**
         * Create a new instance.
         *
         * @param string|\DateTimeInterface $startDate starting datepoint
         * @param string|\DateTimeInterface $endDate   ending datepoint
         *
         * @throws \LogicException If $startDate is greater than $endDate
         */
        public function __construct($startDate, $endDate)
        {
            $this->startDate = self::validateDatePoint($startDate);
            $this->endDate   = self::validateDatePoint($endDate);

            if (1 === self::compareDate($this->startDate, $this->endDate)) {
                throw new LogicException(
                    'The ending datepoint must be greater or equal to the starting datepoint'
                );
            }
        }

        /**
         * Validate a DateTimeImmutable object.
         *
         * @param string|\DateTimeInterface $datetime
         *
         * @return \DateTimeImmutable
         */
        protected static function validateDatePoint($datetime)
        {
            if ($datetime instanceof DateTimeImmutable) {
                return $datetime;
            }

            if ($datetime instanceof DateTime) {
                return new DateTimeImmutable($datetime->format(self::DATE_LOCALE), $datetime->getTimeZone());
            }

            return new DateTimeImmutable($datetime);
        }

        /**
         * Compare DateTimeInterface objects including microseconds
         *
         * @param \DateTimeInterface $date1
         * @param \DateTimeInterface $date2
         *
         * @return int
         */
        protected static function compareDate(DateTimeInterface $date1, DateTimeInterface $date2)
        {
            if ($date1 > $date2) {
                return 1;
            }

            if ($date1 < $date2) {
                return -1;
            }

            $micro1 = $date1->format('u');
            $micro2 = $date2->format('u');

            if ($micro1 > $micro2) {
                return 1;
            }

            if ($micro1 < $micro2) {
                return -1;
            }

            return 0;
        }

        /**
         * Create a PeriodLib object from a Year and a Week.
         *
         * @param int $year
         * @param int $week index from 1 to 53
         *
         * @return self A new instance
         */
        public static function createFromWeek($year, $week)
        {
            $startDate = new DateTimeImmutable(
                self::validateYear($year).'W'.sprintf('%02d', self::validateRange($week, 1, 53))
            );

            return new self($startDate, $startDate->add(new DateInterval('P1W')));
        }

        /**
         * Validate a year.
         *
         * @param int $year
         *
         * @return int
         *
         * @throws \InvalidArgumentException If year is not a valid int
         */
        protected static function validateYear($year)
        {
            $year = filter_var($year, FILTER_VALIDATE_INT);

            if (false === $year) {
                throw new InvalidArgumentException("A Year must be a valid int");
            }

            return $year;
        }

        /**
         * Validate a int according to a range.
         *
         * @param int $value the value to validate
         * @param int $min   the minimun value
         * @param int $max   the maximal value
         *
         * @return int
         *
         * @throws \OutOfRangeException If the value is not in the range
         */
        protected static function validateRange($value, $min, $max)
        {
            $res = filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => $min, 'max_range' => $max]]
            );

            if (false === $res) {
                throw new OutOfRangeException("the submitted value is not contained within the valid range");
            }

            return $res;
        }

        /**
         * Create a PeriodLib object from a Year and a Month.
         *
         * @param int $year
         * @param int $month Month index from 1 to 12
         *
         * @return self A new instance
         */
        public static function createFromMonth($year, $month)
        {
            $month     = sprintf('%02s', self::validateRange($month, 1, 12));
            $startDate = new DateTimeImmutable(self::validateYear($year) . '-' . $month . '-01');

            return new self($startDate, $startDate->add(new DateInterval('P1M')));
        }

        /**
         * Create a PeriodLib object from a Year and a Quarter.
         *
         * @param int $year
         * @param int $quarter Quarter Index from 1 to 4
         *
         * @return self A new instance
         */
        public static function createFromQuarter($year, $quarter)
        {
            $month     = sprintf('%02s', ((self::validateRange($quarter, 1, 4) - 1) * 3) + 1);
            $startDate = new DateTimeImmutable(self::validateYear($year) . '-' . $month . '-01');

            return new self($startDate, $startDate->add(new DateInterval('P3M')));
        }

        /**
         * Create a PeriodLib object from a Year and a Quarter.
         *
         * @param int $year
         * @param int $semester Semester Index from 1 to 2
         *
         * @return self A new instance
         */
        public static function createFromSemester($year, $semester)
        {
            $month     = sprintf('%02s', ((self::validateRange($semester, 1, 2) - 1) * 6) + 1);
            $startDate = new DateTimeImmutable(self::validateYear($year) . '-' . $month . '-01');

            return new self($startDate, $startDate->add(new DateInterval('P6M')));
        }

        /**
         * Create a PeriodLib object from a Year and a Quarter.
         *
         * @param int $year
         *
         * @return self A new instance
         */
        public static function createFromYear($year)
        {
            $startDate = new DateTimeImmutable(self::validateYear($year) . '-01-01');

            return new self($startDate, $startDate->add(new DateInterval('P1Y')));
        }

        /**
         * Create a PeriodLib object from a starting point and an interval.
         *
         * @param string|\DateTimeInterface  $startDate start datepoint
         * @param \DateInterval|float|string $duration  The duration. If an numeric is passed, it is
         *                                              interpreted as the duration expressed in seconds.
         *                                              If a string is passed, it must be parsable by
         *                                              `DateInterval::createFromDateString`
         *
         * @return self A new instance
         */
        public static function createFromDuration($startDate, $duration)
        {
            $date = self::validateDatePoint($startDate);

            return new self($date, self::addDuration($date, $duration));
        }

        /**
         * Add a duration to a DateTimeInterface object
         *
         * @param \DateTimeImmutable         $datetime
         * @param \DateInterval|float|string $duration The duration. If an numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         * @return \DateTimeImmutable
         */
        protected static function addDuration(DateTimeImmutable $datetime, $duration)
        {
            if ($duration instanceof DateInterval) {
                return $datetime->add($duration);
            }

            if (false !== filter_var($duration, FILTER_VALIDATE_FLOAT)) {
                return self::addTimestamp($datetime, $duration);
            }

            return $datetime->add(DateInterval::createFromDateString($duration));
        }

        /**
         * Add a timestamp to a DateTimeInterface including microseconds
         *
         * @param \DateTimeImmutable $datetime
         * @param float              $seconds
         *
         * @return \DateTimeImmutable
         */
        protected static function addTimestamp(DateTimeImmutable $datetime, $seconds)
        {
            if (0 > $seconds) {
                throw new InvalidArgumentException('The interval can not be negative');
            }

            if (false !== ($res = filter_var($seconds, FILTER_VALIDATE_INT))) {
                return $datetime->add(new DateInterval('PT' . $res . 'S'));
            }

            $timestamp = explode('.', sprintf('%6f', $seconds));
            $seconds   = (int) $timestamp[0];
            $micro     = $timestamp[1] + $datetime->format('u');

            if ($micro > 1e6) {
                $micro -= 1e6;
                $seconds++;
            }

            $dateEnd = $datetime->add(new DateInterval('PT'.$seconds.'S'));

            return new DateTimeImmutable(
                $dateEnd->format('Y-m-d H:i:s') . "." . sprintf('%06d', $micro),
                $datetime->getTimeZone()
            );
        }

        /**
         * Create a PeriodLib object from a ending datepoint and an interval.
         *
         * @param string|\DateTimeInterface  $endDate  end datepoint
         * @param \DateInterval|float|string $duration The duration. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         *
         * @return self A new instance
         */
        public static function createFromDurationBeforeEnd($endDate, $duration)
        {
            $date = self::validateDatePoint($endDate);

            return new self(self::subDuration($date, $duration), $date);
        }

        /**
         * Substract a duration to a DateTimeInterface object
         *
         * @param \DateTimeImmutable         $datetime
         * @param \DateInterval|float|string $duration The duration. If an numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         * @return \DateTimeImmutable
         */
        protected static function subDuration(DateTimeImmutable $datetime, $duration)
        {
            if ($duration instanceof DateInterval) {
                return $datetime->sub($duration);
            }

            if (false !== filter_var($duration, FILTER_VALIDATE_FLOAT)) {
                return self::subTimestamp($datetime, $duration);
            }

            return $datetime->sub(DateInterval::createFromDateString($duration));
        }

        /**
         * Substract a timestamp to a DateTimeInterface including microseconds
         *
         * @param \DateTimeImmutable $datetime
         * @param float              $seconds
         *
         * @return \DateTimeImmutable
         */
        protected static function subTimestamp(DateTimeImmutable $datetime, $seconds)
        {
            if (0 > $seconds) {
                throw new InvalidArgumentException('The interval can not be negative');
            }

            if (false !== ($res = filter_var($seconds, FILTER_VALIDATE_INT))) {
                return $datetime->sub(new DateInterval('PT' . $res . 'S'));
            }

            $timestamp = explode('.', (string) sprintf('%6f', $seconds));
            $seconds   = (int) $timestamp[0];
            $micro     = $datetime->format('u') - $timestamp[1];

            if (0 > $micro) {
                $micro += 1e6;
                $seconds++;
            }

            $dateEnd = $datetime->sub(new DateInterval('PT' . $seconds . 'S'));

            return new DateTimeImmutable(
                $dateEnd->format('Y-m-d H:i:s') . '.' . sprintf('%06d', $micro),
                $datetime->getTimeZone()
            );
        }

        /**
         * String representation of a PeriodLib using ISO8601 Time interval format
         *
         * @return string
         */
        public function __toString()
        {
            $utc = new DateTimeZone('UTC');

            return $this->startDate
            ->setTimeZone($utc)
            ->format(self::DATE_ISO8601) . '/' . $this->endDate->setTimeZone($utc)->format(self::DATE_ISO8601);
        }

        /**
         * implement JsonSerializable interface
         *
         * @return array
         */
        public function jsonSerialize()
        {
            return [
                'startDate' => new DateTime($this->startDate->format(self::DATE_LOCALE), $this->startDate->getTimeZone()),
                'endDate'   => new DateTime($this->endDate->format(self::DATE_LOCALE), $this->endDate->getTimeZone()),
            ];
        }

        /**
         * Returns the starting datepoint.
         *
         * @return \DateTimeImmutable
         */
        public function getStartDate()
        {
            return $this->startDate;
        }

        /**
         * Returns the ending datepoint.
         *
         * @return \DateTimeImmutable
         */
        public function getEndDate()
        {
            return $this->endDate;
        }

        /**
         * Returns the PeriodLib duration as expressed in seconds
         *
         * @return float
         */
        public function getTimestampInterval()
        {
            $end   = $this->endDate->getTimestamp() + (int) $this->endDate->format('u') * 1e-6;
            $start = $this->startDate->getTimestamp() + (int) $this->startDate->format('u') * 1e-6;

            return (float) sprintf('%f', $end - $start);
        }

        /**
         * Returns the PeriodLib duration as a DateInterval object.
         *
         * @return \DateInterval
         */
        public function getDateInterval()
        {
            return $this->startDate->diff($this->endDate);
        }

        /**
         * Allows iteration over a set of dates and times,
         * recurring at regular intervals, over the PeriodLib object.
         *
         * @param \DateInterval|float|string $duration The interval. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         *
         * @return \Generator
         */
        public function getDatePeriod($duration)
        {
            $date = $this->startDate;
            do {
                yield $date;
                $date = self::addDuration($date, $duration);
            } while (-1 === self::compareDate($date, $this->endDate));
        }

        /**
         * Split the current object into PeriodLib objects according to the given interval
         *
         * @param \DateInterval|float|string $duration The interval. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         *
         * @return \Generator containing PeriodLib objects
         */
        public function split($duration)
        {
            $startDate = $this->startDate;
            do {
                $endDate = self::addDuration($startDate, $duration);

                if (1 === self::compareDate($endDate, $this->endDate)) {
                    $endDate = $this->endDate;
                }

                yield new self($startDate, $endDate);

                $startDate = $endDate;
            } while (-1 === self::compareDate($startDate, $this->endDate));
        }

        /**
         * Tells whether two PeriodLib share the same datepoints.
         *
         * @param PeriodLib $period
         *
         * @return bool
         */
        public function sameValueAs(PeriodLib $period)
        {
            return 0 === self::compareDate($this->startDate, $period->startDate) && 0 === self::compareDate($this->endDate, $period->endDate);
        }

        /**
         * Tells whether two PeriodLib object abuts
         *
         * @param PeriodLib $period
         *
         * @return bool
         */
        public function abuts(PeriodLib $period)
        {
            return in_array(0, [
                self::compareDate($this->startDate, $period->endDate),
                self::compareDate($this->endDate, $period->startDate)
            ]);
        }

        /**
         * Tells whether two PeriodLib objects overlaps.
         *
         * @param PeriodLib $period
         *
         * @return bool
         */
        public function overlaps(PeriodLib $period)
        {
            if ($this->abuts($period)) {
                return false;
            }

            return -1 === self::compareDate($this->startDate, $period->endDate) &&  1 === self::compareDate($this->endDate, $period->startDate);
        }

        /**
         * Tells whether a PeriodLib is entirely after the specified index
         *
         * @param PeriodLib|\DateTimeInterface $index
         *
         * @return bool
         */
        public function isAfter($index)
        {
            if ($index instanceof PeriodLib) {
                return -1 < self::compareDate($this->startDate, $index->endDate);
            }

            return 1 === self::compareDate($this->startDate, self::validateDatePoint($index));
        }

        /**
         * Tells whether a PeriodLib is entirely before the specified index
         *
         * @param PeriodLib|\DateTimeInterface $index
         *
         * @return bool
         */
        public function isBefore($index)
        {
            if ($index instanceof PeriodLib) {
                return 1 > self::compareDate($this->endDate, $index->startDate);
            }

            return 1 > self::compareDate($this->endDate, self::validateDatePoint($index));
        }

        /**
         * Tells whether the specified index is fully contained within
         * the current PeriodLib object.
         *
         * @param PeriodLib|\DateTimeInterface $index
         *
         * @return bool
         */
        public function contains($index)
        {
            if ($index instanceof PeriodLib) {
                return $this->contains($index->startDate) && $this->contains($index->endDate);
            }

            $datetime = self::validateDatePoint($index);

            return -1 < self::compareDate($datetime, $this->startDate) && -1 === self::compareDate($datetime, $this->endDate);
        }

        /**
         * Compares two PeriodLib objects according to their duration.
         *
         * @param PeriodLib $period
         *
         * @return int
         */
        public function compareDuration(PeriodLib $period)
        {
            return self::compareDate(
                $this->endDate,
                self::addTimestamp(
                    $this->startDate,
                    $period->getTimestampInterval()
                )
            );
        }

        /**
         * Tells whether the current PeriodLib object duration
         * is greater than the submitted one.
         *
         * @param PeriodLib $period
         *
         * @return bool
         */
        public function durationGreaterThan(PeriodLib $period)
        {
            return 1 === $this->compareDuration($period);
        }

        /**
         * Tells whether the current PeriodLib object duration
         * is less than the submitted one.
         *
         * @param PeriodLib $period
         *
         * @return bool
         */
        public function durationLessThan(PeriodLib $period)
        {
            return -1 === $this->compareDuration($period);
        }

        /**
         * Tells whether the current PeriodLib object duration
         * is equal to the submitted one
         *
         * @param PeriodLib $period
         *
         * @return bool
         */
        public function sameDurationAs(PeriodLib $period)
        {
            return 0 === $this->compareDuration($period);
        }

        /**
         * Create a PeriodLib object from a Year and a Quarter.
         *
         * @param PeriodLib $period
         *
         * @return \DateInterval
         */
        public function dateIntervalDiff(PeriodLib $period)
        {
            return $this->endDate->diff(
                $this->withDuration(
                    $period->getTimestampInterval()
                )->endDate
            );
        }

        /**
         * Returns the difference between two PeriodLib objects expressed in seconds
         *
         * @param PeriodLib $period
         *
         * @return float
         */
        public function timestampIntervalDiff(PeriodLib $period)
        {
            return $this->getTimestampInterval() - $period->getTimestampInterval();
        }

        /**
         * Returns a new PeriodLib object with a new included starting datepoint.
         *
         * @param string|\DateTimeInterface $startDate datepoint
         *
         * @return self A new instance
         */
        public function startingOn($startDate)
        {
            return new self($startDate, $this->endDate);
        }

        /**
         * Returns a new PeriodLib object with a new ending datepoint.
         *
         * @param string|\DateTimeInterface $endDate datepoint
         *
         * @return self A new instance
         */
        public function endingOn($endDate)
        {
            return new self($this->startDate, $endDate);
        }

        /**
         * Returns a new PeriodLib object with a new ending datepoint.
         *
         * @param \DateInterval|float|string $duration The duration. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         *
         * @return self A new instance
         */
        public function withDuration($duration)
        {
            return new self($this->startDate, self::addDuration($this->startDate, $duration));
        }

        /**
         * Returns a new PeriodLib object with an added interval
         *
         * @param \DateInterval|float|string $duration The duration. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         *
         * @return self A new instance
         */
        public function add($duration)
        {
            return new self($this->startDate, self::addDuration($this->endDate, $duration));
        }

        /**
         * Returns a new PeriodLib object with a Removed interval
         *
         * @param \DateInterval|float|string $duration The duration. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         *
         * @return self A new instance
         */
        public function sub($duration)
        {
            return new self($this->startDate, self::subDuration($this->endDate, $duration));
        }

        /**
         * Returns a new PeriodLib object adjacent to the current PeriodLib
         * and starting with its ending datepoint.
         * If no duration is provided the new PeriodLib will be created
         * using the current object duration
         *
         * @param \DateInterval|float|string $duration The duration. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         * @return self A new instance
         */
        public function next($duration = null)
        {
            if (is_null($duration)) {
                $duration = $this->getTimestampInterval();
            }

            return new self(
                $this->endDate,
                self::addDuration(
                    $this->endDate,
                    $duration
                )
            );
        }

        /**
         * Returns a new Period object adjacent to the current Period
         * and ending with its starting datepoint.
         * If no duration is provided the new Period will have the
         * same duration as the current one
         *
         * @param \DateInterval|float|string $duration The duration. If a numeric is passed, it is
         *                                             interpreted as the duration expressed in seconds.
         *                                             If a string is passed, it must be parsable by
         *                                             `DateInterval::createFromDateString`
         * @return self A new instance
         */
        public function previous($duration = null)
        {
            if (is_null($duration)) {
                $duration = $this->getTimestampInterval();
            }

            return new self(
                self::subDuration(
                    $this->startDate,
                    $duration
                ),
                $this->startDate
            );
        }

        /**
         * Merges one or more PeriodLib objects to return a new PeriodLib object.
         *
         * The resultant object englobes the largest duration possible.
         *
         * @param PeriodLib $period
         * @param PeriodLib ...$periods one or more PeriodLib objects
         *
         * @return self A new instance
         */
        public function merge(PeriodLib $period)
        {
            return array_reduce(func_get_args(), function (PeriodLib $carry, PeriodLib $period) {
                if (1 === self::compareDate($carry->startDate, $period->startDate)) {
                    $carry = $carry->startingOn($period->startDate);
                }

                if (-1 === self::compareDate($carry->endDate, $period->endDate)) {
                    $carry = $carry->endingOn($period->endDate);
                }

                return $carry;
            }, clone $this);
        }

        /**
         * Computes the intersection between two PeriodLib objects.
         *
         * @param PeriodLib $period
         *
         * @return self A new instance
         */
        public function intersect(PeriodLib $period)
        {
            if ($this->abuts($period)) {
                throw new LogicException('Both object should not abuts');
            }

            return new self(
                (1 === self::compareDate($period->startDate, $this->startDate)) ? $period->startDate : $this->startDate,
                (-1 === self::compareDate($period->endDate, $this->endDate)) ? $period->endDate : $this->endDate
            );
        }

        /**
         * Computes the gap between two PeriodLib objects.
         *
         * @param PeriodLib $period
         *
         * @return self A new instance
         */
        public function gap(PeriodLib $period)
        {
            if (1 === self::compareDate($period->startDate, $this->startDate)) {
                return new self($this->endDate, $period->startDate);
            }

            return new self($period->endDate, $this->startDate);
        }

        /**
         * Computes the difference between two overlapsing PeriodLib objects
         * and return an array containing the difference expressed as PeriodLib objects
         * The array will:
         * - be empty if both objects have the same datepoints
         * - contain one PeriodLib object if both objects share one datepoint
         * - contain two PeriodLib objects if both objects share no datepoint
         *
         * @param PeriodLib $period
         *
         * @return PeriodLib[]
         *
         * @throws \LogicException If both object do not overlaps
         */
        public function diff(PeriodLib $period)
        {
            if (! $this->overlaps($period)) {
                throw new LogicException('Both PeriodLib objects should overlaps');
            }

            $res = [
                self::createFromDatepoints($this->startDate, $period->startDate),
                self::createFromDatepoints($this->endDate, $period->endDate),
            ];

            return array_values(array_filter($res, function (PeriodLib $period) {
                return self::compareDate($period->startDate, $period->endDate);
            }));
        }

        /**
         * Create a new Period instance given two datepoints
         * The datepoints will be used as to allow the creation of
         * a Period object
         *
         * @param string|\DateTimeInterface $datePoint1 datepoint
         * @param string|\DateTimeInterface $datePoint2 datepoint
         *
         * @return self A new instance
         */
        protected static function createFromDatepoints($datePoint1, $datePoint2)
        {
            $datePoint1 = self::validateDatePoint($datePoint1);
            $datePoint2 = self::validateDatePoint($datePoint2);
            if (1 === self::compareDate($datePoint1, $datePoint2)) {
                return new self($datePoint2, $datePoint1);
            }

            return new self($datePoint1, $datePoint2);
        }
    }
