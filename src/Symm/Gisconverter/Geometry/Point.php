<?php

namespace Symm\Gisconverter\Geometry;

use Symm\Gisconverter\Exceptions\InvalidFeature;
use Symm\Gisconverter\Exceptions\OutOfRangeLon;
use Symm\Gisconverter\Exceptions\OutOfRangeLat;
use Symm\Gisconverter\Exceptions\UnimplementedMethod;

class Point extends Geometry
{
    const name = "Point";

    private $lon;
    private $lat;

    public function __construct($coords)
    {
        if (count($coords) < 2) {
            throw new InvalidFeature(__CLASS__, "Point must have two coordinates");
        }

        $lon = $coords[0];
        $lat = $coords[1];

        if (!$this->checkLon($lon)) {
            throw new OutOfRangeLon($lon);
        }

        if (!$this->checkLat($lat)) {
            throw new OutOfRangeLat($lat);
        }

        $this->lon = (float) $lon;
        $this->lat = (float) $lat;
    }

    public function __get($property)
    {
        if ($property == "lon") {
            return $this->lon;
        } elseif ($property == "lat") {
            return $this->lat;
        } else {
            throw new \Exception("Undefined property");
        }
    }

    /**
     * A point has no childs
     * @return null
     */
    public function getComponents()
    {
        return null;
    }

    /**
     * A point has no childs
     * @return int solid zero
     */
    public function numGeometries()
    {
        return 0;
    }

    public function toWKT()
    {
        return strtoupper(static::name) . "({$this->lon} {$this->lat})";
    }

    public function toKML()
    {
        return "<" . static::name . "><coordinates>{$this->lon},{$this->lat}</coordinates></" . static::name . ">";
    }

    public function toGPX($mode = null)
    {
        if (!$mode) {
            $mode = "wpt";
        }

        if ($mode != "wpt") {
            throw new UnimplementedMethod(__FUNCTION__, get_called_class());
        }

        return "<wpt lon=\"{$this->lon}\" lat=\"{$this->lat}\"></wpt>";
    }

    public function toGeoArray()
    {
        return array ('type' => static::name, 'coordinates' => array($this->lon, $this->lat));
    }

    public function toGeoJSON()
    {
        return json_encode((object) $this->toGeoArray());
    }

    public function equals(Geometry $geom)
    {
        if (get_class($geom) != get_class($this)) {
            return false;
        }

        return $geom->lat == $this->lat && $geom->lon == $this->lon;
    }

    private function checkLon($lon)
    {
        if (!is_numeric($lon)) {
            return false;
        }

        if ($lon < -180 || $lon > 180) {
            return false;
        }

        return true;
    }

    private function checkLat($lat)
    {
        if (!is_numeric($lat)) {
            return false;
        }

        if ($lat < -90 || $lat > 90) {
            return false;
        }

        return true;
    }

    /**
     * The WKB representation of a point is its coordinates packed as double precision
     * @return String concatenation of lon and lat packed as double precision
     */
    public function writeWKB()
    {

        $wkb = pack('dd', $this->lon, $this->lat);
        return $wkb;
    }
    
    public function toWKB($write_as_hex = false)
    {
        $wkb = pack('c', 1);
        $wkb.= pack('L', 1);
        $wkb.= $this->writeWKB();

        if ($write_as_hex) {
            $unpacked = unpack('H*', $wkb);
            return $unpacked[1];
        } else {
            return $wkb;
        }
    }
}
