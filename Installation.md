# Setting up the IDA-server #


## Requirements: ##


  * Linux server (may or may not work with other OS's)
  * Apache
  * PHP5 + mbstring + XML
  * pear package MDB2 + database spesific driver
  * PostgreSQL (or Mysql)


## Setting PHP for UTF-8 ##

Following settings should be in use in php.ini:

`mbstring.internal_encoding = UTF-8`

`mbstring.http_input = auto`

`mbstring.http_output = UTF-8`

`mbstring.encoding_translation = On`

`mbstring.detect_order = auto`

`default_charset = "UTF-8"`


## Setting database ##

  * you must create a database for IDA:
    * mysql: CREATE DATABASE idadev CHARACTER SET utf8 COLLATE utf8\_swedish\_ci;
    * postgresql: createdb _your\_databasename_


## Installation from SVN ##

  * first checkout the code from svn:
> > `svn checkout http://idaframework.googlecode.com/svn/trunk/ idaframework-read-only`


  * make a new directory in your server document root (for example **ida\_test**)
  * symlink idaframerwork-read-only/**idacore** under ida\_test.
> > `ln -s /YOUR_HOME_DIR/idaframework-read-only/idacore /YOUR_DOCUMENT_ROOT/ida_test/`

  * symlink idaframerwork-read-only/**xmldemo** under ida\_test.
  * **copy** configuration file from idaframework-read-only/idacore/ to ida\_test/:
> > `cp idaframework-read-only/idacore/config.php /YOUR_DOCUMENT_ROOT/ida_test/`


## Settings in config.php ##
Edit ida\_test/config.php and define:
  * database name
  * database user
  * database password
  * databasetype (mysql/psql)

Be careful when editing config.php, since it must be a valid php file!

## Setup ##
Now everything should be ready and setup can be launched:

- Aim your browser to /ida\_test/server/init/

First page tests things and if everything seems to OK, then just proceed. In ideal case, you should end up to the introduction page.

When you want to update, just run **svn up** in idaframework-read-only directory.
This works as long as there are no changes in database structure (there will be changes at some point).


---

TROUBLESHOOTING

---


Q: I'm not able to connect to database!
A: Make sure that you have:
  * database running
  * installed PHP module for your database (like php5-pgsql)
  * installed MDB2 with PEAR
  * installed MDB2#driver\_for\_your\_db (like MDB2\_Driver\_pgsql)

NOTE: There is also a command line test script in /idacore/server/. You can use it by saying:
php cli\_test.php