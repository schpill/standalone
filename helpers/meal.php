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

    class MealLib
    {
        public function get($segment_id)
        {
            $item = [];

            $dbType = Model::Segmenttype()->where(['name', '=', 'resto_plat'])->first(true);
            $row    = Model::Segment()->find((int) $segment_id);

            if ($row && $dbType) {
                $attributes = Model::Attribute()
                ->where(['segmenttype_id', '=', (int) $dbType->id])
                ->orderByName()
                ->exec();

                $datas = repo('segment')->getData((int) $segment_id, true);

                foreach ($attributes as $att) {
                    $item[(string) $att['name']] = isAke((array) $datas, (string) $att['name'], null);
                }

                $item['name'] = (string) $row->name;

                $item = array_merge((array) $item, (array) $datas);
            }

            return $item;
        }

        public function geo($segment_id)
        {
            return $this->attributes('geo', (int) $segment_id);
        }

        public function type($segment_id)
        {
            return $this->attributes('type', (int) $segment_id);
        }

        public function populatePivots($segment_id)
        {
            $geos   = $this->geo((int) $segment_id);
            $types  = $this->type((int) $segment_id);

            Model::Mealgeo()->where(['segment_id', '=', (int) $segment_id])->exec(true)->delete();
            Model::Mealtype()->where(['segment_id', '=', (int) $segment_id])->exec(true)->delete();

            if (!empty($geos)) {
                foreach ($geos as $resto_geo_id) {
                    Model::Mealgeo()->create([
                        'segment_id' => (int) $segment_id,
                        'resto_geo_id' => (int) $resto_geo_id
                    ])->save();
                }
            }

            if (!empty($types)) {
                foreach ($types as $resto_type_id) {
                    Model::Mealtype()->create([
                        'segment_id' => (int) $segment_id,
                        'resto_type_id' => (int) $resto_type_id
                    ])->save();
                }
            }
        }

        public function getMealsFromGeo($resto_geo_id)
        {
            $collection = [];

            if (!is_integer($resto_geo_id)) {
                return $collection;
            }

            $ids = repo('segment')->getAllFamilyIds((int) $resto_geo_id);
            $ids[] = $resto_geo_id;

            $segments = Model::Mealgeo()->where(['resto_geo_id', 'IN', implode(',', $ids)])->exec();

            foreach ($segments as $segment) {
                $collection[] = $this->get((int) $segment['segment_id']);
            }

            return $collection;
        }

        public function getMealsFromType($resto_type_id)
        {
            $collection = [];

            if (!is_integer($resto_type_id)) {
                return $collection;
            }

            $ids = repo('segment')->getAllFamilyIds((int) $resto_type_id);
            $ids[] = $resto_type_id;

            $segments = Model::Mealtype()->where(['resto_type_id', 'IN', implode(',', $ids)])->exec();

            foreach ($segments as $segment) {
                $collection[] = $this->get((int) $segment['segment_id']);
            }

            return $collection;
        }

        public function getItems()
        {
            $collection = [];

            $dbType = Model::Segmenttype()->where(['name', '=', 'resto_plat'])->first(true);

            if ($dbType) {
                $segments = Model::Segment()->where(['segmenttype_id', '=', (int) $dbType->id])->exec(true);

                foreach ($segments as $segment) {
                    $item       = $this->get((int) $segment['id']);

                    $is_item    = isAke($item, 'is_item', false);

                    if (true === $is_item) {
                        $collection[] = $item;
                    }
                }
            }

            return $collection;
        }

        private function attributes($type, $segment_id)
        {
            $collection = [];

            $item = $this->get((int) $segment_id);

            if (!empty($item)) {
                foreach ($item as $k => $v) {
                    if (!is_null($v) && is_numeric($v)) {
                        if (fnmatch($type . '_attribut_*', $k)) {
                            $collection[] = (int) $v;
                        }
                    }
                }
            }

            return $collection;
        }
    }
