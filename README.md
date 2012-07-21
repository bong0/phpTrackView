==== phpTrackView ====

This project aims to develop a webapplication featuring
  * visualization of GPS-Tracks on OSM Hike&Bike, CycleMap and optionally other maps
    * underlying topography indication featuring countourlines and shadows
  * a rich set of statistical graphs such as speed over time and elevation over distance
    * support for heart-rate and cadence data
  * statistics over each track and tracksegment such as average moving speed or total elevation gain
  * synced hovering on graph to mapped trackpoint
  * multi-track uploading
  * preprocessing of input trackdata and efficient caching of output
  * download of original trackdata

The UI is based on http://twitter.github.com/bootstrap/ .

=== Testing dev version ===

You can test the project using php 5.4:

    php -S localhost:8080 route.php

