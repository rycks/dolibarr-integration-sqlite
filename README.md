# DOLIBARR ERP & CRM -- Special sqlite version for integration tests

## LICENSE

Dolibarr is released under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version (GPL-3+).

See the [COPYING](https://github.com/Dolibarr/dolibarr/blob/develop/COPYING) file for a full copy of the license.

Other licenses apply for some included dependencies. See [COPYRIGHT](https://github.com/Dolibarr/dolibarr/blob/develop/COPYRIGHT) for a full list.

## OFFICIAL REPOSITORY

Please go to https://github.com/Dolibarr/dolibarr

## CREDITS

Dolibarr is the work of many contributors over the years and uses some fine PHP libraries.

See [COPYRIGHT](https://github.com/Dolibarr/dolibarr/blob/develop/COPYRIGHT) file.

# Special version - sqlite + ready to tests for integrators & developpers

```
git clone https://github.com/rycks/dolibarr-integration-sqlite.git
cd dolibarr-integration-sqlite
cp htdocs/conf/conf.php_sqlite htdocs/conf/conf.php
php -S localhost:8080 -d display_errors=1 -d error_reporting=E_ALL -d session.save_path=/tmp -t htdocs/
```

Open your browser to http://localhost:8080/ login admin password adminadmin

That's all !

## CAP-REL

https://cap-rel.fr is one of french dolibarr preferred partner's

