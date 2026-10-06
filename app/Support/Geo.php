<?php

namespace App\Support;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class Geo
{
    /**
     * Raw haversine great-circle distance (km) SQL fragment from the given point to
     * each row's latitude/longitude columns — for reuse in ->whereRaw()/->havingRaw().
     */
    public static function haversineFormula(float $lat, float $lng): string
    {
        return "(6371 * acos(GREATEST(-1, LEAST(1,
                cos(radians({$lat})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$lng}))
                + sin(radians({$lat})) * sin(radians(latitude))
            ))))";
    }

    /**
     * Haversine great-circle distance (km) from the given point to each row's
     * latitude/longitude columns, aliased as "distance_km" for ->orderBy()/->addSelect().
     */
    public static function haversineExpression(float $lat, float $lng): Expression
    {
        return DB::raw(self::haversineFormula($lat, $lng).' AS distance_km');
    }
}
