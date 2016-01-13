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

    class PoiLib
    {
        public function set($name, $address, $sellzone_id, $country = 250)
        {
            return lib('utils')->rememberAge('poi.' . sha1(serialize(func_get_args())), function ($name, $address, $sellzone_id, $country) {
                $poi = Model::Poi()->firstOrCreate([
                    'name'          => (string) $name,
                    'address'       => (string) $address,
                    'sellzone_id'   => (int) $sellzone_id,
                    'country_id'    => (int) $country
                ]);

                $coords = lib('geo')->getCoords($address, $country, false);
                $poi->setLat((double) $coords['lat']);
                $poi->setLng((double) $coords['lng']);
                $poi->setLat1((double) $coords['lat1']);
                $poi->setLng1((double) $coords['lng1']);
                $poi->setLat2((double) $coords['lat2']);
                $poi->setLng2((double) $coords['lng2']);

                unset($coords['lat']);
                unset($coords['lng']);
                unset($coords['lat1']);
                unset($coords['lng1']);
                unset($coords['lat2']);
                unset($coords['lng2']);
                unset($coords['country_id']);

                $poi->setInfos($coords);

                $poi->save();

                return $poi->toArray();
            }, 3600 * 24 * 180, [$name, $address, $sellzone_id, $country]);
        }

        public function getFromSellzone($sellzone_id)
        {
            return Model::Poi()->select('name')->where(['sellzone_id', '=', (int) $sellzone_id])->exec();
        }
    }
