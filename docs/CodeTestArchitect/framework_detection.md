# Framework Detection Reference

## Python
- Preferred: pytest
- Fallback: unittest if project uses it
- Detect via: requirements.txt, pyproject.toml, imports, existing tests
- If missing: suggest `pip install pytest`
- Coverage: detect `coverage`; else `pip install coverage`

## PHP
- Preferred: PHPUnit
- Detect via composer.json require-dev: phpunit/phpunit
- Alternative: Pest if pestphp/pest is present
- If missing: `composer require --dev phpunit/phpunit`
- Coverage requires Xdebug or PCOV

## Bash
- Preferred: bats
- Detect via .bats files or bats commands
- If missing: instruct to install bats
- Coverage optional: kcov or bashcov
