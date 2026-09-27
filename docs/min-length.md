# What is the smallest body worth compressing?

**`min_length`, 2048 bytes by default** — and the comparison is strictly greater, so a
body of exactly 2048 bytes is left alone:

```php
return strlen($content) > $this->minLength();
```

## Why a floor at all

Every encoding adds framing — the gzip header and trailer alone are 18 bytes — and the
client pays decode time. On a small body the framing plus the CPU can exceed the saving,
which is how a "performance" feature makes a response slower. The floor is the point
below which the question is not worth asking.

The number is a policy choice, not a measurement: 2048 bytes is roughly where a JSON
payload carries a list rather than a single record, which is the shape this middleware
was written for. Changing it is a one-line edit in two places — the config file and the
README, which the [config-doc test](../tests/Unit/Config/ConfigDocTest.php) requires to
agree.

Nothing else guards the number: a floor that cannot be read is refused rather than defaulted, and
a floor that is simply small is a decision the operator made rather than a fault.

## What the floor also gates

The check is one of four in `validateResponse()`, and it runs **last**, after the
response has been established to be a non-binary, non-streamed, successful response with
a string body:

1. not a `BinaryFileResponse` or `StreamedResponse`
2. `isSuccessful()`
3. `getContent()` is a string
4. `strlen($content) > min_length`

The order matters. `Response::getContent()` returns `false` for a streamed or binary
response, and reading the length before ruling those out was a `TypeError` on every
streamed and file response in an app where compression was enabled.

## A floor cannot quietly become zero

The value is read as `env('RESPONSE_COMPRESSION_MIN_LENGTH', 2048)`, and Laravel's `env()` leaves
a numeric `.env` value as a **string**. `CompressResponse::minLength()` used to require `is_int()`
and fall back to `0`, so a host app that set

```dotenv
RESPONSE_COMPRESSION_MIN_LENGTH=4096
```

got a floor of **zero** — everything larger than 0 bytes compressed, which is the opposite of what
a floor is for — and nothing anywhere said so.

It is now read through `Config::int()`, which accepts the digit string `.env` produces and refuses
anything else, so the floor is either 4096 or an exception naming the key: it is never 0 unless 0
was configured. `0` is still a legitimate setting, which is why the default was never a safe place
to land.

The rule behind it — what is read, what is refused, and what still takes a documented default — is
recorded in [config-reading.md](config-reading.md), with the `.env` evidence in
[env-types.md](env-types.md).
