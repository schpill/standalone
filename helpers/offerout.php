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

    class OfferoutLib
    {
        public function validate($offerout_id, $reselleremployee_id)
        {
            $error = [];

            try {
                $offerout = Model::Offerout()->findOrFail((int) $offerout_id);
            } catch (Exception $e) {
                $error['error'] = 'no_offer_out';

                return $error;
            }

            try {
                $reselleremployee = Model::Reselleremployee()->findOrFail((int) $reselleremployee_id);
            } catch (Exception $e) {
                $error['error'] = 'employee_unknown';

                return $error;
            }

            try {
                $offerin = Model::Offerin()->findOrFail((int) $offerout->offerin_id);
            } catch (Exception $e) {
                $error['error'] = 'no_offer_out';

                return $error;
            }

            if ($reselleremployee->reseller_id != $offerout->reseller_id) {
                $error['error'] = 'employee_unknown';

                return $error;
            }

            $offerout->reselleremployee_id = (int) $reselleremployee_id;

            if (isset($offerout->start) && isset($offerout->end)) {
                $isLock = redis()->get('lock.offerout.' . $reselleremployee_id);

                if (!empty($isLock)) {
                    for ($i = 0; $i < 10; $i++) {
                        $isLock = redis()->get('lock.offerout.' . $reselleremployee_id);

                        if (empty($isLock)) {
                            break;
                        }

                        sleep(0.2);
                    }

                    if (!empty($isLock)) {
                        $error['error'] = 'is_lock';

                        return $error;
                    }
                } else {
                    redis()->set('lock.offerout.' . $reselleremployee_id, true);
                    redis()->expire('lock.offerout.' . $reselleremployee_id, 10);
                }

                $check = $this->employeeIsYetAvailable((int) $offerout_id, (int) $reselleremployee_id);

                if (!$check) {
                    $error['error'] = 'already_taken';
                    redis()->del('lock.offerout.' . $reselleremployee_id);

                    return $error;
                } else {
                    lib('agenda')->addAppointment(
                        (int) $offerout->start,
                        (int) $offerout->end,
                        (int) $reselleremployee->reseller_id,
                        (int) $reselleremployee_id,
                        (int) $offerout_id
                    );

                    redis()->del('lock.offerout.' . $reselleremployee_id);
                }
            }

            if (isset($offerin->account_id)) {
                $offerout->account_id = (int) $offerin->account_id;
                $offerout = $offerout->save();
            }

            if (isset($offerin->company_id)) {
                $offerout->company_id = (int) $offerin->account_id;
                $offerout = $offerout->save();
            }

            return $this->status('TO_PAY', $offerin, $offerout);
        }

        public function paid($offerout_id)
        {
            $error = [];

            try {
                $offerout = Model::Offerout()->findOrFail((int) $offerout_id);
            } catch (Exception $e) {
                $error['error'] = 'no_offer_out';

                return $error;
            }

            try {
                $offerin = Model::Offerin()->findOrFail((int) $offerout->offerin_id);
            } catch (Exception $e) {
                $error['error'] = 'no_offer_in';

                return $error;
            }

            return $this->status('PAID', $offerin, $offerout);
        }

        public function status($status, $offerin, $offerout)
        {
            $offerin->status_id     = (int) lib('status')->getId('offerin', strtoupper($status));
            $offerout->status_id    = (int) lib('status')->getId('offerout', strtoupper($status));

            $offerin->save();
            $offerout->save();

            return ['return' => true];
        }

        public function erase($offerout_id)
        {
            try {
                $offerout = Model::Offerout()->findOrFail((int) $offerout_id);
            } catch (Exception $e) {
                return false;
            }

            $appointment = Model::Appointment()->where(['offerout_id', '=', (int) $offerout_id])->first(true);

            if ($appointment) {
                $appointment->delete();
            }

            $offerout->status_id = (int) lib('status')->getId('offerout', 'ERASE');

            return $offerout->save();
        }

        public function employeeIsYetAvailable($offerout_id, $rselleremployee_id)
        {
            try {
                $offerout = Model::Offerout()->findOrFail((int) $offerout_id);
            } catch (Exception $e) {
                return false;
            }

            if (isset($offerout->start) && isset($offerout->end)) {
                $employees = lib('offerin')->getEmployeesCan((int) $offerout->start, (int) $offerout->end, (int) $offerout->reseller_id);

                return in_array($rselleremployee_id, $employees) ? true : false;
            }

            return true;
        }

        public function isYetAvailable($offerout_id)
        {
            try {
                $offerout = Model::Offerout()->findOrFail((int) $offerout_id);
            } catch (Exception $e) {
                return false;
            }

            if (isset($offerout->start) && isset($offerout->end)) {
                $employees = lib('offerin')->getEmployeesCan((int) $offerout->start, (int) $offerout->end, (int) $offerout->reseller_id);

                return !empty($employees) ? true : false;
            }

            return true;
        }

        public function refresh($offerout_id)
        {
            try {
                $offerout = Model::Offerout()->findOrFail((int) $offerout_id);
            } catch (Exception $e) {
                return false;
            }

            if (isset($offerout->start) && isset($offerout->end)) {
                $employees = lib('offerin')->getEmployeesCan((int) $offerout->start, (int) $offerout->end, (int) $offerout->reseller_id);

                $offerout->reselleremployees = $employees;
                $offerout = $offerout->save();
            }

            return $offerout;
        }
    }
