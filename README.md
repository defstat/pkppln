# PKP Preservation Network Staging Server

## Requirements

 * PHP >= 8.1
 * MySQL >= 8.0
 * Git
 * Composer
 * ClamAv >= 0.105
 * Yarn
 
This application has been tested with Apache and mod_php and php-fpm. It 
may work with various Nginx setups, but they are untested and unsupported. These 
instructions do not include steps for installing or configuring the 
prerequisites listed above.

## Install

Fetch the most recent code and leave it 
somewhere accessible to the web. The instructions below assume that the application
will be accessed at https://localhost/pn

```bash
$ git clone -b https://github.com/pkp/pn
$ mv pn /var/www/html/pn
$ cd /var/www/html/pn
$ git submodule update --init
```

Create a MySQL user and database, and give the user access to the database.

```sql
CREATE DATABASE IF NOT EXISTS pn;
CREATE USER IF NOT EXISTS pn@localhost;
GRANT ALL ON pn.* TO pn@localhost;
SET PASSWORD FOR pn@localhost = PASSWORD('abc123');
```

Install the yarn and composer dependencies. The `composer install` step 
will ask for some configuration parameters. Use whatever user name and password 
you used to create the database above. The remainder of the defaults should 
work fine.

```bash
$ yarn install
$ composer install --quiet
```

Set the file permissions for various directories. These directions assume that the 
web server runs as the user _apache_.

```bash
for dir in var/logs var/cache var/sessions data;
do
    mkdir -p $dir
    sudo setfacl -R -m u:apache:rwX $dir
    sudo setfacl -dR -m u:apache:rwX $dir
done
```

Finally, create the database tables:

```bash
$ ./bin/console doctrine:schema:update --force
```

And an administrator user:

```bash
$ ./bin/console nines:user:create admin@example.com
$ ./bin/console nines:user:promote admin@example.com ROLE_ADMIN
$ ./bin/console nines:user:password
$ ./bin/console nines:user:activate
```


You should be able to login at https://localhost/pn/public

## Quality Tools

PHP Unit

`./vendor/bin/phpunit`

`./vendor/bin/phpunit --coverage-html=web/docs/coverage`

Sami

`sami -vv update --force sami.php`

PHP CS Fixer

`php-cs-fixer fix`


## Deployment

- To avoid handling permission requirements from the Symfony framework, it's advised to deploy the files in the home folder of an user.
- Install the ClamAv antivirus (e.g. `apt-get install clamav clamav-daemon -y`).
- Create a MySQL database (the system has been tested against MySQL 8.0, but higher versions, as well as MariaDB, should work fine).
- Setup the settings below at the `.env` file:
  - `PN_CLAMD_SOCKET`: The path to ClamAV UNIX socket (e.g. `/var/run/clamav/clamd.ctl`).
  - `DATABASE_URL`: Database URL (e.g. `mysql://user:password@localhost:3306/database?serverVersion=8.0`).
  - `APP_ENV`: Must be changed to `prod`.
  - `ROUTE_PROTOCOL`, `ROUTE_HOST` and `ROUTE_BASE`: Change the values based on the hostname.
  - `PN_DATA_DIR`: Valid and writable directory path, this is where the deposits will be stored.
  - `PN_SERVICE_URI`: The `LOCKSS-O-MATIC` service URL.
  - `PN_UUID`: The secret ID from the `LOCKSS-O-MATIC` service.
  - `MAILER_DSN`: Mail configuration (see: https://symfony.com/doc/current/mailer.html#transport-setup).
  - `APP_SECRET`, `SYMFONY_DECRYPTION_SECRET`: Ensure they have a good entropy for extra security (e.g. http://nux.net/secret).
- The staging server is also able to hold deposits made by applications above the version `pn.max_accepted_version` (located at the `config\services.yaml`).