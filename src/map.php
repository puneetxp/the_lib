<?php
class Map{
  public static function getBoundingBox($latitude, $longitude, $range = 0.5) {
      $earth_radius = 6371; // Earth radius in km
  
      $lat_range = $range / $earth_radius;
      $lon_range = $range / ($earth_radius * cos(deg2rad($latitude)));
  
      $min_lat = $latitude - rad2deg($lat_range);
      $max_lat = $latitude + rad2deg($lat_range);
      $min_lon = $longitude - rad2deg($lon_range);
      $max_lon = $longitude + rad2deg($lon_range);
  
      return [
          'min_lat' => $min_lat,
          'max_lat' => $max_lat,
          'min_lon' => $min_lon,
          'max_lon' => $max_lon,
      ];
  }
}
