-- Run once as the MySQL root user, after `php artisan migrate` has created the tables.
-- Change the password, then put the same one in deploy/freeradius/sql.conf.
--
-- FreeRADIUS gets the minimum it needs: it reads logins and writes usage reports.
-- It cannot touch tenants, wallets, payments or anything else in the Laravel database.

CREATE USER IF NOT EXISTS 'radius'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME';
CREATE USER IF NOT EXISTS 'radius'@'localhost' IDENTIFIED BY 'CHANGE_ME';

GRANT SELECT ON captiveportalapi.radcheck      TO 'radius'@'127.0.0.1', 'radius'@'localhost';
GRANT SELECT ON captiveportalapi.radreply      TO 'radius'@'127.0.0.1', 'radius'@'localhost';
GRANT SELECT ON captiveportalapi.radusergroup  TO 'radius'@'127.0.0.1', 'radius'@'localhost';
GRANT SELECT ON captiveportalapi.radgroupcheck TO 'radius'@'127.0.0.1', 'radius'@'localhost';
GRANT SELECT ON captiveportalapi.radgroupreply TO 'radius'@'127.0.0.1', 'radius'@'localhost';
GRANT SELECT ON captiveportalapi.nas           TO 'radius'@'127.0.0.1', 'radius'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE ON captiveportalapi.radacct TO 'radius'@'127.0.0.1', 'radius'@'localhost';
GRANT INSERT ON captiveportalapi.radpostauth TO 'radius'@'127.0.0.1', 'radius'@'localhost';

FLUSH PRIVILEGES;
