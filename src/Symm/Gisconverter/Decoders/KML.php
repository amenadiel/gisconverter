<?php

namespace Symm\Gisconverter\Decoders;

use Symm\Gisconverter\Exceptions\InvalidText;
use Symm\Gisconverter\Geometry\Point;
use Symm\Gisconverter\Geometry\MultiPoint;
use Symm\Gisconverter\Geometry\MultiLineString;
use Symm\Gisconverter\Geometry\LinearRing;
use Symm\Gisconverter\Geometry\MultiPolygon;
use Symm\Gisconverter\Geometry\GeometryCollection;

class KML extends XML {
    static protected function parsePoint($xml) {
        $coordinates = static::_extractCoordinates($xml);
        $coords = preg_split('/,/', (string)$coordinates[0]);
        return array_map("trim", $coords);
    }

    static protected function parseLineString($xml) {
        $components = array();
        $coordinates = static::_extractCoordinates($xml);
        foreach (preg_split('/\s+/', trim((string)$coordinates[0])) as $compstr) {
            $coords = preg_split('/,/', $compstr);
            $components[] = new Point($coords);
        }
        return $components;
    }

    static protected function parseLinearRing($xml) {
        return static::parseLineString($xml);
    }

    static protected function parsePolygon($xml) {
        $ring = array();
        foreach (static::childElements($xml, 'outerboundaryis') as $elem) {
            $ring = array_merge($ring, static::childElements($elem, 'linearring'));
        }

        if (count($ring) != 1) {
            throw new InvalidText(__CLASS__);
        }

        $components = array(new LinearRing(static::parseLinearRing($ring[0])));
        foreach (static::childElements($xml, 'innerboundaryis') as $elem) {
            foreach (static::childElements($elem, 'linearring') as $ring) {
                $components[] = new LinearRing(static::parseLinearRing($ring[0]));
            }
        }
        return $components;
    }

    static protected function parseMultiGeometry($xml) {
        $components = array();
        foreach ($xml->children() as $child) {
            $components[] = static::_geomFromXML($child);
        }
        return $components;
    }

    static protected function _extractCoordinates($xml) {
        $coordinates = static::childElements($xml, 'coordinates');
        if (count($coordinates) != 1) {
            throw new InvalidText(__CLASS__);
        }
        return $coordinates;
    }

    static protected function _geomFromXML($xml) {
        $nodename = strtolower($xml->getName());
        if ($nodename == "kml" or $nodename == "document" or $nodename == "placemark") {
            return static::_childsCollect($xml);
        }

        foreach (array("Point", "LineString", "LinearRing", "Polygon", "MultiGeometry") as $kml_type) {
            if (strtolower($kml_type) == $nodename) {
                $type = $kml_type;
                break;
            }
        }

        if (!isset($type)) {
            throw new InvalidText(__CLASS__);
        }

        try {
            $components = call_user_func(array('static', 'parse'.$type), $xml);
        } catch(InvalidText $e) {
            throw new InvalidText(__CLASS__);
        } catch(\Exception $e) {
            throw $e;
        }

        if ($type == "MultiGeometry") {
            if (count($components)) {
                $possibletype = $components[0]::name;
                $sametype = true;
                foreach (array_slice($components, 1) as $component) {
                    if ($component::name != $possibletype) {
                        $sametype = false;
                        break;
                    }
                }
                if ($sametype) {
                    switch ($possibletype) {
                        case "Point":
                            return new MultiPoint($components);
                            break;
                        case "LineString":
                            return new MultiLineString($components);
                            break;
                        case "Polygon":
                            return new MultiPolygon($components);
                            break;
                        default:
                            break;
                    }
                }
            }
            return new GeometryCollection($components);
        }

        $constructor = 'Symm\\Gisconverter\\Geometry\\' . $type;
        return new $constructor($components);
    }
}
