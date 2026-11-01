<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\GuardIndex;

/*
|--------------------------------------------------------------------------
| Bug 13 - PHPDoc Accuracy Exploration Test
|--------------------------------------------------------------------------
|
| This test documents Bug 13: PHPDoc mismatch in GuardIndex::declaration().
|
| CURRENT PHPDoc: @return array{section: string, line: int}|null
| ACTUAL RETURN: array{section: string, line: int, name: ?string}|null
|
| The method returns a 'name' field but the PHPDoc doesn't document it.
| This causes PHPStan to report "Offset 'name' does not exist" on valid accesses
| at lines 422, 572, and 591 in GuardIndex.php.
|
| This exploration test documents the bug by demonstrating:
| 1. The actual return value includes the 'name' field
| 2. The callers access this field
| 3. Static analysis would report errors on these valid accesses
|
| EXPECTED ON UNFIXED CODE: This test documents the issue - it will pass
| because we're just documenting what the code actually does vs what PHPDoc says.
| The real bug is caught by PHPStan, not by runtime behavior.
|
*/

/**
 * @guards-index test
 *
 * documents Bug 13: PHPDoc in GuardIndex::declaration() is incomplete
 */
it('documents Bug 13 - GuardIndex::declaration() returns name field not documented in PHPDoc', function (): void {
    // Read the GuardIndex.php source to examine the declaration() method
    $guardIndexFile = packageRoot().'/tests/Support/GuardIndex.php';
    $contents = file_get_contents($guardIndexFile);

    // Find the PHPDoc for declaration()
    expect($contents)->toContain('private static function declaration(string $contents, array $block): ?array');

    // Document the bug:
    // The PHPDoc on line 1388 currently says:
    // @return array{section: string, line: int}|null

    // But the actual return statement (lines 1402-1406) includes:
    // return [
    //     'section' => $match[1][0],
    //     'name' => $name === '' ? null : $name,  // <-- This field is NOT in PHPDoc
    //     'line' => substr_count($contents, "\n", 0, max($at, 1)) + 1,
    // ];

    // Verify the actual return includes 'name' field
    expect($contents)->toContain("'name' => \$name === '' ? null : \$name,")
        ->and($contents)->toContain("'section' => \$match[1][0],")
        ->and($contents)->toContain("'line' => substr_count");

    // This proves the method returns THREE fields but PHPDoc only documents TWO
    // The missing field: name: ?string

    expect(true)->toBeTrue('Bug 13 documented: declaration() returns name field not in PHPDoc');
});

it('documents Bug 13 - actual callers access the name field at lines 422, 572, 591', function (): void {
    // Read GuardIndex.php to verify the callers actually access 'name' field
    $guardIndexFile = packageRoot().'/tests/Support/GuardIndex.php';
    $contents = file_get_contents($guardIndexFile);

    // Verify line 422: $declaration['name'] access in validation
    expect($contents)->toContain("\$declaration['name']")
        ->and($contents)->toContain("if (\$declaration['section'] === self::GUARD && \$declaration['name'] === null)")
        ->and($contents)->toContain("\$declaration['name'] ?? ''");

    // This proves that:
    // 1. The code correctly accesses $declaration['name'] in multiple places
    // 2. These are valid accesses - the array IS returned with a 'name' field
    // 3. But PHPStan sees: @return array{section: string, line: int}|null
    //    and reports: "Offset 'name' does not exist on array{section: string, line: int}"

    // The fix is simple: update the PHPDoc to:
    // @return array{section: string, line: int, name: ?string}|null

    expect(true)->toBeTrue('Bug 13 confirmed: callers access name field that PHPDoc does not document');
});

it('documents Bug 13 - PHPDoc should match actual return structure', function (): void {
    // This test documents what the CORRECT PHPDoc should be

    // CURRENT (INCORRECT) PHPDoc:
    $currentPhpDoc = '@return array{section: string, line: int}|null';

    // CORRECT PHPDoc should be:
    $correctPhpDoc = '@return array{section: string, line: int, name: ?string}|null';

    // The difference:
    $missingField = 'name: ?string';

    // Document the fix needed
    expect($currentPhpDoc)->not->toContain($missingField)
        ->and($correctPhpDoc)->toContain($missingField);

    // After the fix at task 3.1:
    // 1. Update tests/Support/GuardIndex.php line 1388
    // 2. Change PHPDoc from: @return array{section: string, line: int}|null
    // 3. To: @return array{section: string, line: int, name: ?string}|null
    // 4. Run PHPStan - should show zero errors on declaration() callers

    expect(true)->toBeTrue('Bug 13 fix documented: add name: ?string to PHPDoc');
});
