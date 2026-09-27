# What should an out-of-range compression level do?

**Fall back to a documented default rather than pass the value through.** Each encoder
validates the configured level against the range its own library accepts:

| Encoder | Accepted | Fallback |
|---|---|---|
| `GzipEncoder` | −1 … 9 | 5 |
| `BrotliEncoder` | 0 … 11 | 5 |
| `ZstdEncoder` | 1 … 22 | 3 |

```php
return Config::intInRange('response-compression.gzip.level', -1, 9, 5);
```

The range and the fallback are the same decision as before; what changed is the reader underneath
them. A level that is *set but cannot be read* — `'high'`, or an array, or a missing key the
package always ships — is now refused rather than defaulted, because a default standing in for a
value somebody wrote is a typo that reaches production as behaviour. A level that is readable and
out of range still takes the documented default, which is the case this document is about. The
distinction is recorded in [config-reading.md](config-reading.md).

## Why the guard is there

The three libraries do not agree on what to do with a nonsense level. `gzencode()`
clamps, the brotli extension's `brotli_compress()` and zstd's `zstd_compress()` are less
forgiving, and each has a different range. A silently-clamped value and a
silently-failed call are both worse than a known default: the first hides a typo in a
config file, the second hides it in a response body.

The fallbacks also act as the documented defaults, so the number in the config file and
the number this code uses when the config is unusable are the same.

## The brotli level is read from the key the config ships

`BrotliEncoder::level()` used to ask for a key that does not exist:

```
src/Encoders/BrotliEncoder.php:  config('response-compression.brotli.level');
config/response-compression.php: 'br' => ['level' => env('RESPONSE_COMPRESSION_BROTLI_LEVEL', 5), …]
```

The config key is `br`, the reader asked for `brotli`, so the lookup returned `null` and the
brotli level was **always 5** — whatever `br.level` said and whatever
`RESPONSE_COMPRESSION_BROTLI_LEVEL` was set to. `gzip` and `zstd` read the keys they name.

The repair took the first of the two choices: the reader moved to `response-compression.br.level`
and the key stayed where it was. Renaming the key instead would have been a published-key break
for a bug nobody could have been relying on, and `br` is the name both the config section and the
middleware already use for this algorithm — the `brotli` spelling existed in exactly one place,
the line that was wrong.

It had to be fixed rather than left, in the end, because a reader that refuses what it cannot read
would have refused the *missing* key on every brotli response.

## Related

An env-set level had a second problem even where the key was right — `is_int()` rejected the
string `.env` produces, so the setting was ignored. See [env-types.md](env-types.md), and
[config-reading.md](config-reading.md) for what is read and what is refused.
