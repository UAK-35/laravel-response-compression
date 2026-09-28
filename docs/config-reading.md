# What happens to a configuration value that cannot be read?

**It is refused, with the key, the value and the file to fix it in — rather than replaced with a
default.** A value that *is* what the key requires is read, whatever PHP type it arrived as.
Anything else stops the application at boot.

The three silent fallbacks this replaced were all wrong in the same direction, and all wrong
quietly:

| Config key | Was | Effect |
|---|---|---|
| `min_length` | `is_int($value) ? $value : 0` | a floor of **0** — every response compressed |
| `gzip.level`, `zstd.level` | `is_int($value) ? $value : 5 / 3` | the setting was ignored |
| `br.level` | read from a key that does not exist | ignored twice over — see [level-clamping.md](level-clamping.md) |

None of them said anything, which is the part worth fixing: a default that stands in for a value
somebody wrote is a typo that reaches production as behaviour.

## Three outcomes, not two

| The key | Outcome |
|---|---|
| **absent** | refused by `Config::int()` and `Config::string()`, and a documented default taken by the readers that have one (`intOr`, `boolOr`, `stringOr`, `stringListOr`) |
| **present, unreadable** | refused by every reader, defaults included |
| **present, out of range** | the documented default, and deliberately — see below |

Absent is not the same fault as unreadable. The package always ships `algorithm` and `min_length`,
so their absence means the config was never merged or published, and the message says to publish
it. A key that is *there* and says something the code cannot use is somebody's decision, and it is
refused whether or not a default was offered.

The two keys that have no default are the two whose absence is a broken installation. Everything
else carries its documented default, because the alternative — refusing a key a host app has never
heard of — would make every future config key a breaking change.

## Why a `.env` value is read rather than refused

`env()` hands a numeric value back as a **string** ([env-types.md](env-types.md) has the
evidence), so refusing every string would refuse the package's own published defaults: the value
would be right and only its type would be wrong. A string of digits is therefore *read* —
`'4096'` is 4096 — and never coerced around: `'4096a'` is refused rather than truncated to 4096,
and `'abc'` is refused rather than becoming `0`.

Booleans need no such bridge: `env()` converts `true` and `false` itself, and the reader accepts
PHP's own vocabulary (`1`, `0`, `true`, `false`, `on`, `off`, `yes`, `no`, `''`) and nothing
wider. `filter_var(…, FILTER_VALIDATE_BOOLEAN)` alone answers `false` for `'maybe'`, which is the
same silent substitution one level down.

## Why an out-of-range level is still defaulted

A level of 40 is readable and merely wrong, and the three compression libraries each answer a
wrong level differently — one clamps, two fail the call. A known default beats a library-specific
clamp, and [level-clamping.md](level-clamping.md) records that decision with the ranges.

So the line is between *unreadable* and *out of range*, not between wrong and not wrong: one is
refused because the code cannot tell what was meant, the other takes the documented default
because it can.

## Where it is checked

`ResponseCompressionServiceProvider::boot()` calls `Config::validate()`, which reads every key the
package uses. A bad value therefore stops the application at start-up instead of on whichever
request first needs it — the same value is far easier to diagnose as a start-up failure than as a
response body nobody can explain.

The middleware and the encoders read through the same `Config` readers, so a value changed at
runtime is refused there too rather than only at boot.

`enable_logging` is deliberately left out of the boot check. It is read by the middleware rather
than at boot ([unwired-config.md](unwired-config.md)), because a key whose only effect is a line
in a log should not be able to stop an application from starting. Its reader is `boolOr()` like
the other switches, and a reader that refuses what it cannot read does not stop refusing it here:
a typo in that value costs the response that needed the decision, not the boot. That is the trade
the exclusion makes, and it is worth knowing which half of it you get.

## The evidence

`tests/Unit/Support/ConfigTest.php` holds both halves of the rule: a digit string is read and a
`'4096a'` is refused; a level of 40 takes the default and a level of `'high'` is refused; a
`min_length` of `'4096'` leaves a 3000-byte response alone, which is the defect this started from.
The readers are exercised through `Config` directly, so the tests do not depend on the middleware
or on a config file being published.
