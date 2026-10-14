# Test Structure Reference (Updated)

## Python (pytest)
- Test paths must mirror the source file structure.
  Example:
    src/models/user.py -> tests/models/test_user.py
    app/services/payment.py -> tests/services/test_payment.py
- Rules:
  - Determine the file's path relative to the project root.
  - Strip top-level folders like src/ or app/ if appropriate, but preserve subfolders.
  - Recreate the same subfolder hierarchy inside tests/.
  - Test filename format: test_<originalname>.py
- Test style:
  - Use plain pytest functions.
  - Use pytest.raises for exception testing.
  - Use parametrization when beneficial.
  - Mock filesystem, environment, network, or I/O.

## PHP (PHPUnit)
- Test paths must mirror the code structure.
  Example:
    app/Models/User.php -> tests/Models/UserTest.php
    src/Services/Payment/Processor.php -> tests/Services/Payment/ProcessorTest.php
- Namespace-based rule:
  - Tests keep the same namespace substructure under tests/.
- Test style:
  - Test class name: <ClassName>Test
  - One class per test file.
  - Use PHPUnit assertions and mocks.

## Bash (bats)
- Test paths should also mirror script structure.
  Example:
    scripts/deploy/build.sh -> tests/scripts/deploy/test_build.bats
- Test style:
  - @test "description" { run ./script arg; [ "$status" -eq 0 ]; }
  - Validate stdout/stderr.
  - Use temp directory for side-effect isolation.
  - Mock dangerous commands when needed.
