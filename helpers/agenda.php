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

    namespace Thin;

    use Datetime;

    class AgendaLib
    {
        public function addEvent($start, $end, $reseller_id, $reselleremployee_id, $offerout_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reseller_id)) {
                throw new Exception("reseller_id must be an integer id.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if (!is_integer($offerout_id)) {
                throw new Exception("An event must have an interger offerout_id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $time = lib('time');

            $midnightStart  = (int) $time->createFromTimestamp((int) $start)->startOfDay()->getTimestamp();
            $midnightEnd    = (int) $time->createFromTimestamp((int) $end)->startOfDay()->getTimestamp();

            if ($midnightStart <> $midnightEnd) {
                throw new Exception("start and end must be in the same day. Please cut your event.");
            }

            $dispo = Model::Availability()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
            ->between('start', (int) $start, (int) $end)
            ->between('end', (int) $start, (int) $end)
            ->first(true);

            if ($dispo) {
                $keep = $dispo;

                $dispo->delete();

                $appointment = Model::Appointment()->create([
                    'offerout_id'           => (int) $offerout_id,
                    'reseller_id'           => (int) $reseller_id,
                    'reselleremployee_id'   => (int) $reselleremployee_id,
                    'start'                 => (int) $start,
                    'end'                   => (int) $end
                ])->save();

                if ($start != $keep->start) {
                    $availability = $this->addAvailability(
                        (int) $keep->start,
                        (int) $start,
                        (int) $reseller_id,
                        (int) $reselleremployee_id
                    );

                    $event->attach(
                        $availability,
                        ['start' => (int) $keep->start, 'end' => (int) $start]
                    );
                }

                if ($end != $keep->end) {
                    $availability = $this->addAvailability(
                        (int) $end,
                        (int) $keep->end,
                        (int) $reseller_id,
                        (int) $reselleremployee_id
                    );

                    $event->attach(
                        $availability,
                        ['start' => (int) $end, 'end' => (int) $keep->end]
                    );
                }

                return true;
            }

            return false;
        }

        public function delEvent($start, $end, $reselleremployee_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $event = Model::Appointment()
            ->where(['start', '=', (int) $start])
            ->where(['end', '=', (int) $end])
            ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
            ->first(true);

            if ($event) {
                $keep = $event->assoc();
                /* on récupère les dispos concomittentes à l'event s'il y en a et on les supprime */
                $pivots = $event->pivots(Model::Availability()->model())->exec(true);

                if (!empty($pivots)) {
                    foreach ($pivots as $pivot) {
                        $availability = $pivot->availability();

                        if ($availability) {
                            $availability->detach($event);
                            $availability->delete();
                        }
                    }
                }

                $event->delete();

                $day        = (string) lib('time')->createFromTimestamp((int) $start)->frenchDay();
                $midnight   = (int) lib('time')
                ->createFromTimestamp((int) $start)
                ->startOfDay()
                ->getTimestamp();

                /* On récupère les horaires pour le jour */
                $schedules = Model::Schedule()
                ->where(['day', '=', (string) $day])
                ->where(['reseller_id', '=', (int) $keep['reseller_id']])
                ->exec();

                foreach ($schedules as $schedule) {
                    $amStart    = $this->transform($schedule['am_start'], (int) $midnight);
                    $amEnd      = $this->transform($schedule['am_end'], (int) $midnight);

                    $pmStart    = $this->transform($schedule['pm_start'], (int) $midnight);
                    $pmEnd      = $this->transform($schedule['pm_end'], (int) $midnight);

                    /* on récupère les events du matin et on vérifie si les dispos sont à jour sinon on les ajoute */
                    $amEvents = Model::Appointment()
                    ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                    ->between('start', (int) $amStart, (int) $amEnd)
                    ->between('end', (int) $amStart, (int) $amEnd)
                    ->exec();

                    foreach ($amEvents as $amEvent) {
                        if ($amEvent['start'] != $amStart) {
                            $this->addAvailability(
                                (int) $amStart,
                                (int) $amEvent['start'],
                                (int) $keep['reseller_id'],
                                (int) $reselleremployee_id
                            );
                        }

                        if ($amEvent['end'] != $amEnd) {
                            $this->addAvailability(
                                (int) $amEvent['start'],
                                (int) $amEnd,
                                (int) $keep['reseller_id'],
                                (int) $reselleremployee_id
                            );
                        }
                    }

                    /* on récupère les events de l'après-midi et on vérifie si les dispos sont à jour sinon on les ajoute */
                    $pmEvents = Model::Appointment()
                    ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                    ->between('start', (int) $pmStart, (int) $pmEnd)
                    ->between('end', (int) $pmStart, (int) $pmEnd)
                    ->exec();

                    foreach ($pmEvents as $pmEvent) {
                        if ($pmEvents['start'] != $pmStart) {
                            $this->addAvailability(
                                (int) $pmStart,
                                (int) $pmEvents['start'],
                                (int) $keep['reseller_id'],
                                (int) $reselleremployee_id
                            );
                        }

                        if ($pmEvents['end'] != $pmEnd) {
                            $this->addAvailability(
                                (int) $pmEvents['start'],
                                (int) $pmEnd,
                                (int) $keep['reseller_id'],
                                (int) $reselleremployee_id
                            );
                        }
                    }
                }
            }
        }

        public function getAvailabilities($start, $end, $resellers)
        {
            $collection = $tuples = [];

            if (!is_integer($start)) {
                throw new Exception("Start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("End must be an integer timestamp.");
            }

            if ($start > $end) {
                throw new Exception("Start must be lower than end.");
            }

            if (!Arrays::is($resellers)) {
                return $collection;
            }

            if (!empty($resellers)) {
                $rows = Model::Availability()
                ->where(['reseller_id', 'IN', implode(',', $resellers)])
                ->between('start', (int) $start, (int) $end)
                ->between('end', (int) $start, (int) $end)
                ->orderByStart()
                ->exec();

                foreach ($rows as $row) {
                    if (!Arrays::in($row['reseller_id'], $tuples)) {
                        $tuples[] = $row['reseller_id'];
                        $collection[] = $row;
                    }
                }
            }

            return $collection;
        }

        public function getAvailabilitiesByResellerId($start, $end, $reseller_id)
        {
            if (!is_integer($reseller_id)) {
                throw new Exception("reseller_id must be an integer id.");
            }

            if (!is_integer($start)) {
                throw new Exception("Start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("End must be an integer timestamp.");
            }

            if ($start > $end) {
                throw new Exception("Start must be lower than end.");
            }

            $rows = Model::Availability()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->between('start', (int) $start, (int) $end)
            ->between('end', (int) $start, (int) $end)
            ->orderByStart()
            ->exec();

            return $rows;
        }

        public function addAvailability($start, $end, $reseller_id, $reselleremployee_id)
        {
            $time = lib('time');

            $midnightStart  = (int) $time->createFromTimestamp((int) $start)->startOfDay()->getTimestamp();
            $midnightEnd    = (int) $time->createFromTimestamp((int) $end)->startOfDay()->getTimestamp();

            if ($midnightStart <> $midnightEnd) {
                throw new Exception("start and end must be in the same day. Please cut your event.");
            }

            return Model::Availability()->insert([
                'reseller_id'           => (int) $reseller_id,
                'reselleremployee_id'   => (int) $reselleremployee_id,
                'start'                 => (int) $start,
                'end'                   => (int) $end
            ]);
        }

        public function delAvailability($start, $end, $reseller_id, $reselleremployee_id = null)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reseller_id)) {
                throw new Exception("reseller_id must be an integer id.");
            }

            if (!empty($reselleremployee_id)) {
                if (!is_integer($reselleremployee_id)) {
                    throw new Exception("reselleremployee_id must be an integer id.");
                }
            }

            if ($start > $end) {
                throw new Exception("Start must be lower than end.");
            }

            $query = Model::Availability()
            ->where(['start', '=', (int) $start])
            ->where(['end', '=', (int) $end])
            ->where(['reseller_id', '=', (int) $reseller_id]);

            if (!empty($reselleremployee_id)) {
                $query->where(['reselleremployee_id', '=', (int) $reselleremployee_id]);
            }

            return $query->exec(true)->delete();
        }

        public function initAvailabilities($reseller_id = null, $reselleremployee_id = null)
        {
            set_time_limit(0);
            ini_set('memory_limit', '1024M');

            $start  = lib('time', new Datetime('midnight'));
            $end    = lib('time', new Datetime('+3 month midnight'));

            $q = Model::Availability()->where(['reseller_id', '=', (int) $reseller_id]);

            if (!empty($reselleremployee_id)) {
                if (!is_integer($reselleremployee_id)) {
                    throw new Exception("reselleremployee_id must be an integer.");
                }

                $q->where(['reselleremployee_id', '=', (int) $reselleremployee_id]);
            }

            $dispos = $q->exec(true)->delete();

            for ($i = $start->getTimestamp(); $i < $end->getTimestamp(); $i += (24 * 3600)) {
                $date = lib('time')->createFromTimestamp($i);

                $day = (string) $date->frenchDay();

                /* On récupère les horaires pour le jour */
                $query = Model::Schedule()->where(['day', '=', (string) $day]);

                if (!empty($reseller_id)) {
                    if (!is_integer($reseller_id)) {
                        throw new Exception("reseller_id must be an integer.");
                    }

                    $query->where(['reseller_id', '=', (int) $reseller_id]);
                }

                $schedules = $query->exec();

                foreach ($schedules as $schedule) {
                    $q = Model::Reselleremployee()
                    ->where(['reseller_id', '=', (int) $schedule['reseller_id']]);

                    if (!empty($reselleremployee_id)) {
                        if (!is_integer($reselleremployee_id)) {
                            throw new Exception("reselleremployee_id must be an integer.");
                        }

                        $q->where(['id', '=', (int) $reselleremployee_id]);
                    }

                    $employees = $q->exec();

                    foreach ($employees as $employee) {
                        if ($schedule['am_start'] != 'ferme' && $schedule['am_end'] != 'ferme') {
                            $amStart    = $this->transform($schedule['am_start'], (int) $i);
                            $amEnd      = $this->transform($schedule['am_end'], (int) $i);

                            $availability = $this->addAvailability(
                                (int) $amStart,
                                (int) $amEnd,
                                (int) $schedule['reseller_id'],
                                (int) $employee['id']
                            );

                            $availability->attach(
                                Model::Schedule()->find((int) $schedule['id']),
                                ['start' => (int) $amStart, 'end' => (int) $amEnd]
                            );

                            $this->checkAppointments(
                                (int) $amStart,
                                (int) $amEnd,
                                (int) $employee['id']
                            );

                            $this->checkVacations(
                                (int) $amStart,
                                (int) $amEnd,
                                (int) $employee['id']
                            );
                        }

                        if ($schedule['pm_start'] != 'ferme' && $schedule['pm_end'] != 'ferme') {
                            $pmStart    = $this->transform($schedule['pm_start'], (int) $i);
                            $pmEnd      = $this->transform($schedule['pm_end'], (int) $i);

                            $availability = $this->addAvailability(
                                (int) $pmStart,
                                (int) $pmEnd,
                                (int) $schedule['reseller_id'],
                                (int) $employee['id']
                            );

                            $availability->attach(
                                Model::Schedule()->find((int) $schedule['id']),
                                ['start' => (int) $pmStart, 'end' => (int) $pmEnd]
                            );

                            $this->checkAppointments(
                                (int) $pmStart,
                                (int) $pmEnd,
                                (int) $employee['id']
                            );

                            $this->checkVacations(
                                (int) $pmStart,
                                (int) $pmEnd,
                                (int) $employee['id']
                            );
                        }
                    }
                }
            }
        }

        public function makeAvailabilities($reseller_id = null, $reselleremployee_id = null)
        {
            set_time_limit(0);
            ini_set('memory_limit', '1024M');

            $date = lib('time', new Datetime('+3 month midnight'));

            $day = (string) $date->frenchDay();

            /* On récupère les horaires pour le jour */
            $query = Model::Schedule()->where(['day', '=', (string) $day]);

            if (!empty($reseller_id)) {
                if (!is_integer($reseller_id)) {
                    throw new Exception("reseller_id must be an integer.");
                }

                $query->where(['reseller_id', '=', (int) $reseller_id]);
            }

            $schedules = $query->exec();

            foreach ($schedules as $schedule) {
                $q = Model::Reselleremployee()
                ->where(['reseller_id', '=', (int) $schedule['reseller_id']]);

                if (!empty($reselleremployee_id)) {
                    if (!is_integer($reselleremployee_id)) {
                        throw new Exception("reselleremployee_id must be an integer.");
                    }

                    $q->where(['id', '=', (int) $reselleremployee_id]);
                }

                $employees = $q->exec();

                foreach ($employees as $employee) {
                    if ($schedule['am_start'] != 'ferme' && $schedule['am_end'] != 'ferme') {
                        $amStart    = $this->transform($schedule['am_start'], (int) $date->getTimestamp());
                        $amEnd      = $this->transform($schedule['am_end'], (int) $date->getTimestamp());

                        $availability = $this->addAvailability(
                            (int) $amStart,
                            (int) $amEnd,
                            (int) $schedule['reseller_id'],
                            (int) $employee['id']
                        );

                        $availability->attach(
                            Model::Schedule()->find((int) $schedule['id']),
                            ['start' => (int) $amStart, 'end' => (int) $amEnd]
                        );

                        $this->checkAppointments(
                            (int) $amStart,
                            (int) $amEnd,
                            (int) $employee['id']
                        );

                        $this->checkVacations(
                            (int) $amStart,
                            (int) $amEnd,
                            (int) $employee['id']
                        );
                    }

                    if ($schedule['pm_start'] != 'ferme' && $schedule['pm_end'] != 'ferme') {
                        $pmStart    = $this->transform($schedule['pm_start'], (int) $date->getTimestamp());
                        $pmEnd      = $this->transform($schedule['pm_end'], (int) $date->getTimestamp());

                        $availability = $this->addAvailability(
                            (int) $pmStart,
                            (int) $pmEnd,
                            (int) $schedule['reseller_id'],
                            (int) $employee['id']
                        );

                        $availability->attach(
                            Model::Schedule()->find((int) $schedule['id']),
                            ['start' => (int) $pmStart, 'end' => (int) $pmEnd]
                        );

                        $this->checkAppointments(
                            (int) $pmStart,
                            (int) $pmEnd,
                            (int) $employee['id']
                        );

                        $this->checkVacations(
                            (int) $pmStart,
                            (int) $pmEnd,
                            (int) $employee['id']
                        );
                    }
                }
            }
        }

        /* status = appointment ou vacation */

        public function addVacation($start, $end, $reselleremployee_id, $status = 'appointment')
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an interger timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an interger timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $time = lib('time');

            $midnightStart  = (int) $time->createFromTimestamp((int) $start)->startOfDay()->getTimestamp();
            $midnightEnd    = (int) $time->createFromTimestamp((int) $end)->startOfDay()->getTimestamp();

            if ($midnightStart <> $midnightEnd) {
                throw new Exception("start and end must be in the same day. Please cut your event.");
            }

            $employee = Model::Reselleremployee()->find((int) $reselleremployee_id);

            if ($employee) {
                Model::Vacation()->create([
                    'status_id'             => (int) lib('status')->getId('vacation', $status),
                    'reseller_id'           => (int) $employee->reseller_id,
                    'reselleremployee_id'   => (int) $reselleremployee_id,
                    'start'                 => (int) $start,
                    'end'                   => (int) $end
                ])->save();

                $this->checkVacations(
                    (int) $start,
                    (int) $end,
                    (int) $reselleremployee_id
                );

                return true;
            }

            return false;
        }

        public function delVacation($start, $end, $reselleremployee_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("Start must be lower than end.");
            }

            $employee = Model::Reselleremployee()->find((int) $reselleremployee_id);

            if ($employee) {
                $employee   = $employee->assoc();
                $vacations  = Model::Vacation()
                ->where(['start', '=', (int) $start])
                ->where(['end', '=', (int) $end])
                ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                ->exec(true);

                foreach ($vacations as $vacation) {
                    $pivots = $vacation->pivots(Model::Availability()->model())->exec(true);

                    if (!empty($pivots)) {
                        foreach ($pivots as $pivot) {
                            $availability = $pivot->availability();

                            if ($availability) {
                                $availability->detach($vacation);
                                $availability->delete();
                            }
                        }
                    }

                    $time = lib('time');

                    $day        = (string) $time->createFromTimestamp((int) $vacation->start)->frenchDay();
                    $midnight   = (int) $time->createFromTimestamp((int) $vacation->start)
                    ->startOfDay()
                    ->getTimestamp();

                    $vacation->delete();

                    /* On récupère les horaires pour le jour */
                    $schedules = Model::Schedule()
                    ->where(['day', '=', (string) $day])
                    ->where(['reseller_id', '=', (int) $employee['reseller_id']])
                    ->exec();

                    foreach ($schedules as $schedule) {
                        $amStart    = $this->transform($schedule['am_start'], (int) $midnight);
                        $amEnd      = $this->transform($schedule['am_end'], (int) $midnight);

                        $pmStart    = $this->transform($schedule['pm_start'], (int) $midnight);
                        $pmEnd      = $this->transform($schedule['pm_end'], (int) $midnight);

                        /* on récupère les events du matin et on vérifie si les dispos sont à jour sinon on les ajoute */
                        $amEvents = Model::Appointment()
                        ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                        ->between('start', (int) $amStart, (int) $amEnd)
                        ->between('end', (int) $amStart, (int) $amEnd)
                        ->exec();

                        foreach ($amEvents as $amEvent) {
                            if ($amEvent['start'] != $amStart) {
                                $availability = $this->addAvailability(
                                    (int) $amStart,
                                    (int) $amEvent['start'],
                                    (int) $employee['reseller_id'],
                                    (int) $reselleremployee_id
                                );

                                $availability->attach(
                                    Model::Schedule()->find((int) $schedule['id']),
                                    ['start' => (int) $amStart, 'end' => (int) $amEvent['start']]
                                );
                            }

                            if ($amEvent['end'] != $amEnd) {
                                $availability = $this->addAvailability(
                                    (int) $amEvent['start'],
                                    (int) $amEnd,
                                    (int) $employee['reseller_id'],
                                    (int) $reselleremployee_id
                                );

                                $availability->attach(
                                    Model::Schedule()->find((int) $schedule['id']),
                                    ['start' => (int) $amEvent['start'], 'end' => (int) $amEnd]
                                );
                            }
                        }

                        /* on récupère les events de l'après-midi et on vérifie si les dispos sont à jour sinon on les ajoute */
                        $pmEvents = Model::Appointment()
                        ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                        ->between('start', (int) $pmStart, (int) $pmEnd)
                        ->between('end', (int) $pmStart, (int) $pmEnd)
                        ->exec();

                        foreach ($pmEvents as $pmEvent) {
                            if ($pmEvents['start'] != $pmStart) {
                                $availability = $this->addAvailability(
                                    (int) $pmStart,
                                    (int) $pmEvent['start'],
                                    (int) $employee['reseller_id'],
                                    (int) $reselleremployee_id
                                );

                                $availability->attach(
                                    Model::Schedule()->find((int) $schedule['id']),
                                    ['start' => (int) $pmStart, 'end' => (int) $amEnd]
                                );
                            }

                            if ($pmEvents['end'] != $pmEnd) {
                                $availability = $this->addAvailability(
                                    (int) $pmEvent['end'],
                                    (int) $pmEnd,
                                    (int) $employee['reseller_id'],
                                    (int) $reselleremployee_id
                                );

                                $availability->attach(
                                    Model::Schedule()->find((int) $schedule['id']),
                                    ['start' => (int) $pmEvent['end'], 'end' => (int) $pmEnd]
                                );
                            }
                        }

                        return true;
                    }
                }
            }

            return false;
        }

        /* Vérifie les vacations d'une période pour un employee */
        public function hasVacations($start, $end, $reselleremployee_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $cb = function ($start, $end, $reselleremployee_id) {
                $count = Model::Vacation()
                ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                ->between('start', (int) $start, (int) $end)
                ->between('end', (int) $start, (int) $end)
                ->count();

                return $count > 0 ? true : false;
            };

            return lib('utils')->remember('hasVacations.' . sha1(serialize(func_get_args())), $cb, Model::Vacation()->getAge(), [$start, $end, $reselleremployee_id]);
        }

        public function checkVacations($start, $end, $reselleremployee_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $vacations = Model::Vacation()
            ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
            ->between('start', (int) $start, (int) $end)
            ->between('end', (int) $start, (int) $end)
            ->exec(true);

            /* s'il y a des vacations, on fabrique les dispos qui correspondent pour chacune */
            foreach ($vacations as $vacation) {
                $dispo = Model::Availability()
                ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                ->between('start', (int) $start, (int) $end)
                ->between('end', (int) $start, (int) $end)
                ->exec(true);

                foreach ($dispos as $dispo) {
                    $keep = $dispo;

                    $dispo->delete();

                    if ($start != $keep->start) {
                        $availability = $this->addAvailability(
                            (int) $keep->start,
                            (int) $start,
                            (int) $reseller_id,
                            (int) $reselleremployee_id
                        );

                        $vacation->attach($availability, ['start' => (int) $keep->start, (int) 'end' => $start]);
                    }

                    if ($end != $keep->end) {
                        $availability = $this->addAvailability(
                            (int) $end,
                            (int) $keep->end,
                            (int) $reseller_id,
                            (int) $reselleremployee_id
                        );

                        $vacation->attach($availability, ['start' => (int) $end, 'end' => (int) $keep->end]);
                    }
                }
            }
        }

        public function hasAppointments($start, $end, $reselleremployee_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $cb = function ($start, $end, $reselleremployee_id) {
                $count = Model::Appointment()
                ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                ->between('start', (int) $start, (int) $end)
                ->between('end', (int) $start, (int) $end)
                ->count();

                return $count > 0 ? true : false;
            };

            return lib('utils')->remember('hasAppointments.' . sha1(serialize(func_get_args())), $cb, Model::Appointment()->getAge(), [$start, $end, $reselleremployee_id]);
        }

        public function checkAppointments($start, $end, $reselleremployee_id)
        {
            if (!is_integer($start)) {
                throw new Exception("start must be an integer timestamp.");
            }

            if (!is_integer($end)) {
                throw new Exception("end must be an integer timestamp.");
            }

            if (!is_integer($reselleremployee_id)) {
                throw new Exception("reselleremployee_id must be an integer id.");
            }

            if ($start > $end) {
                throw new Exception("start must be lower than end.");
            }

            $appointemnts = Model::Appointment()
            ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
            ->between('start', (int) $start, (int) $end)
            ->between('end', (int) $start, (int) $end)
            ->exec(true);

            /* s'il y a des rdvs, on fabrique les dispos qui correspondent pour chacune */
            foreach ($appointemnts as $appointemnt) {
                $dispo = Model::Availability()
                ->where(['reselleremployee_id', '=', (int) $reselleremployee_id])
                ->between('start', (int) $start, (int) $end)
                ->between('end', (int) $start, (int) $end)
                ->exec(true);

                foreach ($dispos as $dispo) {
                    $keep = $dispo;

                    $dispo->delete();

                    if ($start != $keep->start) {
                        $availability = $this->addAvailability(
                            (int) $keep->start,
                            (int) $start,
                            (int) $reseller_id,
                            (int) $reselleremployee_id
                        );

                        $appointemnt->attach($availability, ['start' => (int) $keep->start, (int) 'end' => $start]);
                    }

                    if ($end != $keep->end) {
                        $availability = $this->addAvailability(
                            (int) $end,
                            (int) $keep->end,
                            (int) $reseller_id,
                            (int) $reselleremployee_id
                        );

                        $appointemnt->attach($availability, ['start' => (int) $end, 'end' => (int) $keep->end]);
                    }
                }
            }
        }

        /*
            transforme 08_00 en timestamp de minuit + (8 * 3600)
            transforme 14_15 en timestamp de minuit + (14 * 3600) + (15 * 60)
            Renvoie un timestamp ou passe par une exception
            ...
        */
        public function transform($hourMinute, $midnight)
        {
            list($hour, $minute) = explode('_', str_replace(':', '_', $hourMinute), 2);

            if ($hour[0] == '0' && strlen($hour) == 2) {
                $hour = (int) $hour[1];
            } else {
                $hour = (int) $hour;
            }

            if ($minute[0] == '0' && strlen($minute) == 2) {
                $minute = (int) $minute[1];
            } else {
                $minute = (int) $minute;
            }

            if (is_integer($hour) && is_integer($minute)) {
                return (int) $midnight + ($hour * 3600) + ($minute * 60);
            }

            throw new Exception('An error occured.');
        }

        public function addSchedule($reseller_id, $day, $amStart, $amEnd, $pmStart, $pmEnd)
        {
            if (!is_integer($reseller_id)) {
                throw new Exception("reseller_id must be an integer id.");
            }

            Model::Schedule()->create([
                'reseller_id'   => (int) $reseller_id,
                'day'           => (string) $day,
                'am_start'      => (string) $amStart,
                'am_end'        => (string) $amEnd,
                'pm_start'      => (string) $pmStart,
                'pm_end'        => (string) $pmEnd
            ])->save();
        }

        public function delSchedule($reseller_id, $day)
        {
            if (!is_integer($reseller_id)) {
                throw new Exception("reseller_id must be an integer id.");
            }

            $schedule = Model::Schedule()
            ->where(['reseller_id', '=', (int) $reseller_id])
            ->where(['day', '=', (string) $day])
            ->first(true);

            if ($schedule) {
                $pivots = $schedule->pivots(Model::Availability()->model())->exec(true);

                if (!empty($pivots)) {
                    foreach ($pivots as $pivot) {
                        $availability = $pivot->availability();

                        if ($availability) {
                            $availability->detach($schedule);
                            $availability->delete();
                        }
                    }
                }

                $schedule->delete();

                return true;
            }

            return false;
        }

        public function addReminder($model, $when)
        {
            if (!is_integer($when)) {
                throw new Exception('The second argument must be an integr timestamp.');
            }

            if (!is_object($model)) {
                throw new Exception('The first argument must be a model.');
            }

            $db     = $model->_db->db;
            $table  = $model->_db->table;
            $id     = $model->id;

            $reminder = Model::Reminder()->create([
                'object_db'     => (string) $db,
                'object_table'  => (string) $table,
                'object_id'     => (int) $id,
                'when'          => (int) $when,
                'color'         => (string) $color,
            ])->save();

            return $reminder->id;
        }

        public function hasReminder($model)
        {
            if (!is_object($model)) {
                throw new Exception('The first argument must be a model.');
            }

            $db     = $model->_db->db;
            $table  = $model->_db->table;
            $id     = $model->id;

            $count = Model::Reminder()
            ->where(['object_db', '=', (string) $db])
            ->where(['object_table', '=', (string) $table])
            ->where(['object_id', '=', (int) $id])
            ->where(['when', '>=', time()])
            ->count();

            return $count > 0 ? true : false;
        }

        public function getReminders($model)
        {
            $collection = lib('collection');

            if (!is_object($model)) {
                throw new Exception('The first argument must be a model.');
            }

            $db     = $model->_db->db;
            $table  = $model->_db->table;
            $id     = $model->id;

            $rows = Model::Reminder()
            ->where(['object_db', '=', (string) $db])
            ->where(['object_table', '=', (string) $table])
            ->where(['object_id', '=', (int) $id])
            ->where(['when', '>=', time()])
            ->exec();

            foreach ($rows as $row) {
                $item               = [];
                $item['event_type'] = (string) $row['object_table'];
                $item['event_id']   = (string) $row['object_id'];
                $item['when']       = (int) $row['when'];
                $item['color']      = (string) $row['color'];

                $collection[] = $item;
            }

            if (!empty($collection)) {
                $collection->orderBy('when');
            }

            return $collection->toArray();
        }

        public function getZones()
        {
            return \DateTimeZone::listIdentifiers();
        }

        public function addAppointment($start, $end, $reseller_id, $reselleremployee_id, $offerout_id)
        {
            $time = lib('time');

            $midnightStart  = (int) $time->createFromTimestamp((int) $start)->startOfDay()->getTimestamp();
            $midnightEnd    = (int) $time->createFromTimestamp((int) $end)->startOfDay()->getTimestamp();

            if ($midnightStart <> $midnightEnd) {
                throw new Exception("start and end must be in the same day. Please cut your appointment.");
            }

            return Model::Appointment()->create([
                'reseller_id'           => (int) $reseller_id,
                'reselleremployee_id'   => (int) $reselleremployee_id,
                'offerout_id'           => (int) $offerout_id,
                'start'                 => (int) $start,
                'end'                   => (int) $end
            ])->save();
        }

        public function getNextWorkingDay($time = null)
        {
            $time = is_null($time) ? strtotime('tomorrow') : $time;
            /* TO DO VACANCES */

            $year = intval(date('Y', $time));

            $easterDate     = easter_date($year);
            $easterDay      = date('j', $easterDate);
            $easterMonth    = date('n', $easterDate);
            $easterYear     = date('Y', $easterDate);

            $holidays = [
                // Dates fixes
                mktime(0, 0, 0, 1,  1,  $year),  // 1er janvier
                mktime(0, 0, 0, 5,  1,  $year),  // Fête du travail
                mktime(0, 0, 0, 5,  8,  $year),  // Victoire des alliés
                mktime(0, 0, 0, 7,  14, $year),  // Fête nationale
                mktime(0, 0, 0, 8,  15, $year),  // Assomption
                mktime(0, 0, 0, 11, 1,  $year),  // Toussaint
                mktime(0, 0, 0, 11, 11, $year),  // Armistice
                mktime(0, 0, 0, 12, 25, $year),  // Noël

                // Dates variables
                mktime(0, 0, 0, $easterMonth, $easterDay + 1,  $easterYear), /* Lundi de Pâques */
                mktime(0, 0, 0, $easterMonth, $easterDay + 39, $easterYear), /* Jeudi de l'Ascension */
                mktime(0, 0, 0, $easterMonth, $easterDay + 50, $easterYear), /* Lundi de Pentecôte */
            ];

            sort($holidays);

            $when       = lib('time')->createFromTimestamp((int) $time);
            $midnight   = (int) $when->startOfDay()->getTimestamp();
            $day        = (string) $when->frenchDay();

            if ($day == 'samedi') {
                return $this->getNextWorkingDay(strtotime('+2 day', $time));
            } elseif ($day == 'dimanche') {
                return $this->getNextWorkingDay(strtotime('+1 day', $time));
            }

            if (!in_array($midnight, $holidays)) {
                return $time;
            } else {
                return $this->getNextWorkingDay(strtotime('+1 day', $time));
            }
        }
    }
