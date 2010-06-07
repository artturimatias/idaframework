<?php

	// database name
define ('_DBNAME', 'idadev');
	// database user
define ('_DBUSER', 'root');

	// database password
define ('_DBPASS', '');

    // database type (mysql, pgsql)
define ('_DATABASE_TYPE', 'mysql');

    // time zone
date_default_timezone_set('Europe/Helsinki');


    // application data path (images, cache etc.)
define('_APP_DATA_PATH', '/data/');

    // import directory for files NOTE: absolute path 
define('_IMPORT_DIRECTORY', '/home/USERNAME/import/');


	// prefix used in database
define ('_DBPREFIX', 'ida');

    // defines allowed html tags in notes
define ('_ALLOWED_TAGS', "<img><p>");

    // P1F + _PROPERTY_SEPARATOR + is_identified_by
define ('_PROPERTY_SEPARATOR', "_");

    // defines how many rows are displayed in linked objects
define ('_MAX_ROWS', 30);

    // how many characters is shown for notes
define ('_SHORT_NOTE_LENGTH', 100);


    // defines how many records are shown in quick search
define ('_QSEARCH_LIMIT', 30);

    // defines what is the shortest word that is indexed
define ('_MIN_INDEXWORD_LENGTH', 1);

    // defines the lengthest word that is indexed
define ('_MAX_INDEXWORD_LENGTH', 30);

    // thumbnail width
define ('_THUMBNAIL_WIDTH', 300);

    // minithumbnail width
define ('_MINITHUMBNAIL_WIDTH', 100);

    // Event class for queries
define ('_EVENT_CLASSNAME', 'E5.Event');


// DO NOT EDIT THESE!!!

define("NO_ID", 1);
define("FORWARD", 1);
define("BACKFORWARD", 0);
define("AS_ARRAY", 1);
define("CRM_TYPE", 55);
define("CRM_TYPE_LINK", "P2F");
define("TEMP_TABLE_CHAR_LENGTH", 60);
define("SHORT_FORM", 2);

?>
