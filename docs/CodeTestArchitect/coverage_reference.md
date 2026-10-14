# Coverage Reference

## Python
coverage run -m pytest
coverage report -m
coverage html

## PHP
vendor/bin/phpunit --coverage-html coverage/
(Requires Xdebug or PCOV)

## Bash (optional)
kcov coverage/ bats <testfile>
