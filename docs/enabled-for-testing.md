# Why is there a second switch for tests?

**`enabled` is the switch. `enabled_for_testing` additionally gates compression when the
application reports a testing environment, and under test *both* must be true.** Both
default to `true`.

```php
private function compressionAllowed(bool $enabled, bool $enabledForTesting, bool $underTest): bool
{
    return $enabled && (! $underTest || $enabledForTesting);
}
```

## Why a second switch exists at all

Compression changes the bytes a request returns. A host application's own suite may want
to assert exact response bodies while keeping compression on in production, and without
a separate key the only way to do that is to turn compression off everywhere. So the
second key is the "off in tests, on in production" setting, and it is off by the host
app's choice rather than the package's.

The environment is read as `App::environment('testing') || app()->runningUnitTests()`,
because Testbench — which this package's own suite runs on — reports the second without
necessarily reporting the first.

## Why it defaults to `true`

It used to default to `false`, and that made the switch pointless in the worst way:
`enabled()` returned false for **every** test in **every** application, so this
package's suite asserted uncompressed bodies and passed no matter what the middleware
did. Six middleware tests were vacuous — the four "should compress" cases plus the two
brotli and two zstd cases — and they stayed green while:

- `Accept-Encoding` was read as accepting anything when the header was missing,
- the response-side guard read a streamed response's length and threw.

That is what "the tests pass" is worth when the feature under test is switched off: the
assertions are about the *absence* of behaviour, and they hold for the wrong reason.
Repairing the default is what turned those six red, and each one then had to be made to
pass honestly.

An application that wants compression off in its tests sets:

```dotenv
RESPONSE_COMPRESSION_ENABLED_FOR_TESTING=false
```

## Why the decision is a separate function

`compressionAllowed()` takes all three inputs rather than reading them itself. PHPUnit
always reports `runningUnitTests()` as `true`, so an inline check would leave the
non-testing branch of `enabled()` unreachable from the suite — an untestable line in the
middle of the decision under test.

Covered by `it should not compress when compression is disabled` (the `enabled === false`
outcome) and by every compression test in the suite (the under-test, enabled outcome).
