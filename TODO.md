  * http://blueimp.github.com/jQuery-File-Upload/
  * save gpx files as plain text/gzip/bzip2 according to available php modules:
    * 1,1M test.gpx
    * 96K test.gpx.gz
    * 68K test.gpx.bz2
  * better unique file name creation for .gpx.gz / .gpx.bz2 files (1.gpx.gz instead of gpx1.gz)
  * implement toarray function in TrackPoint class of parser.php, drop code for array-only curPoint
  * switch to relative calculation of distance for each point (current to previous)
