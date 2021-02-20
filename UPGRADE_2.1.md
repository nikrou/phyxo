# UPGRADE FROM 2.0.x to 2.1

Release 2.1.0 is the first using Doctrine as database abstraction layer. Data model changes to fit Doctrine and also to simplify queries and objects manipulations.
There's now a new column in configuration table. The new column (**type**) gives the type of data : **string**, **boolean**, **json** or **base64**. Many tables used **enum** to saved boolean. For **mysql** all theses fields are now **tinyint(1)** with **0** for false and **1** for **true**. There's no more functions in php code (boolean_to_string or string_to_boolean) to manipulate theses informations.
Some other columns used also **enum** to store information. Theses fields are also converted to string. All data integrity are maded in class entities.

To upgrade be sure to make a backup of your database.
Go through your admin area and follow admin > Tools > Update
The process to upgrade follows theses steps :

- checks needed directories are writable
- download phyxo 2.1.0 archive
- unzip the archive
- upgrade database (see below for details)
- remove obsoletes files
- remove the cache
- remove the session

## Database upgrade

For all database layers (Mysql, PostgreSQL, SQLite) the new column for configuration table is added. The right type is populated for known config keys.

For Mysql, upgrade process follows theses steps :

1. To convert boolean stored as **enum** to string without losing data, upgrade process use a temporary column. The new column has type string and default value is **1** for string **true** and **0** for **false**. Another query change the default value for a column. The old column is dropped. And finally the new column is renamed.
2. To convert **enum** to string (for others columns that not stored booleans), the process is almost the same except for first query that get the old column value for default.
3. Add constraints between tables
4. Change Mysql storage engine from **MyIsam** to **InnoDb**.
