# What does a value read from `.env` actually arrive as?

**Laravel's `env()` converts booleans, `null` and the empty string — but not numbers.** A numeric
`.env` value arrives as a **string**, so any config key that must be an `int` has to be read as
one rather than merely declared as one.

## The evidence

Run against this package's own dependency tree on 2026-09-30:

```bash
php -r 'require "vendor/autoload.php";
        putenv("DEMO_INT=9"); putenv("DEMO_TRUE=true");
        var_dump(env("DEMO_INT", 5), env("DEMO_TRUE", false));'
```

```
string(1) "9"
bool(true)
```

So `env('RESPONSE_COMPRESSION_GZIP_LEVEL', 5)` returns `'9'` when the variable is `9`, and the
integer `5` when the variable is unset.

## What that cost here

Every key that has to be an `int` was read with `is_int()`, which a string fails:

| Config key | Was read as | Effect of a value set in `.env` |
|---|---|---|
| `min_length` | `is_int($value) ? $value : 0` | **Floor became 0** — everything was compressed. The sharpest of the three. |
| `gzip.level`, `zstd.level` | `is_int($value) ? $value : 5 / 3` | The setting was silently ignored; the default was used. |
| `br.level` | the same, from a key that does not exist | Ignored twice over — see [level-clamping.md](level-clamping.md). |
| `algorithm` | `is_string($value) ? $value : 'gzip'` | Unaffected: `env()` returns a string either way. |
| `multiple_encodings_order` | `is_string($value) ? … : 'br,zstd,gzip'` | Unaffected, for the same reason. |
| `enabled`, `enabled_for_testing`, `try_multiple_encodings` | `filter_var(…, FILTER_VALIDATE_BOOLEAN)` | Unaffected: that filter accepts `true` and `'true'` alike. A value it does not recognise became `false`, though — see below. |

The tests did not catch any of it because the bootstrap sets config values as real integers and
booleans (`config()->set('response-compression.min_length', 1024)`), which is the shape `config()`
returns when a config file's own default is used — and it is the shape `.env` never produces.

## The decision taken

**A value is read; a value that cannot be read is refused** — with the key, the value and the file
to fix it in, rather than replaced by a default. The readers live in
[`Uak35\ResponseCompression\Support\Config`](../src/Support/Config.php), and
[config-reading.md](config-reading.md) is the record: the three outcomes (absent, unreadable, out
of range), why a digit string is read rather than refused, and why a boolean needs no bridge.

Neither of the two options first written down here was taken as written:

- **Cast** — `(int) $value` — turns `'abc'` into `0`, which is the substitution the change exists
  to end.
- **Narrow to a numeric string** — accept `is_int($value) || is_numeric($value)` and cast then —
  is what the reader does, except that `is_numeric()` is a digit test: it is true for `'1e3'` and
  for `' 5'`, and neither is a level anybody wrote. `'+5'` and `'-1'` are accepted because a gzip
  level of −1 is a real thing to configure.

The read still happens in the accessor rather than in the config file, for the reason the choice
was narrowed down for in the first place: normalising in `config()` would make `env()` and the
config value disagree, which is a worse trap than the one being fixed.

`enable_logging` is read through the same `boolOr()` as the other switches, with `false` as its
default; what it is left out of is the boot check rather than the reading
([config-reading.md](config-reading.md), [unwired-config.md](unwired-config.md)).
