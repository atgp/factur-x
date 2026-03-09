CONTRIBUTING
============

Coding standards
----------------

We use the config file **.php_cs.dist** with the version **v3** of **friendsofphp/php-cs-fixer**.

Display proposed fixes without changing files
```bash
vendor/bin/php-cs-fixer fix -v --dry-run
```

Apply the proposed fixes
```bash
vendor/bin/php-cs-fixer fix -v
```

Run the automatic tests
```bash
vendor/bin/phpunit --testdox
```

Run a specific test class
```bash
vendor/bin/phpunit --testdox tests/Unit/WriterTest.php
```

Run a specific test method
```bash
vendor/bin/phpunit --testdox --filter testMethodName tests/Unit/WriterTest.php
```

Run a specific test suite
```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
```

