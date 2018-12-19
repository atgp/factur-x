CONTRIBUTING
============

Coding standards
----------------

We use the config file **.php_cs.dist** with the version **v2.2** of **friendsofphp/php-cs-fixer**.

Display proposed fixes without changing files
```bash
php-cs-fixer fix -v --dry-run ./
```

Apply the proposed fixes
```bash
php-cs-fixer fix -v ./
```

If **php-cs-fixer** is not globally installed, you can run the command :
```bash
vendor/bin/php-cs-fixer fix -v ./
```

