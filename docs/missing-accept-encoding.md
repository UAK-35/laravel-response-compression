# What should a request that names no encoding get?

**Nothing.** The body is served uncompressed. `*` is the only spelling of "I accept
anything".

## What the header can and cannot tell us

`CompressResponse::validateRequestPerAlgoAndUserAgent()` reads
`$request->getEncodings()`. Symfony reads the header as a *list*, so:

| Request | `getEncodings()` |
|---|---|
| no `Accept-Encoding` at all | `[]` |
| `Accept-Encoding:` (empty) | `[]` |
| `Accept-Encoding: *` | `['*']` |
| `Accept-Encoding: gzip, deflate` | `['gzip', 'deflate']` |

An absent header and an empty one are therefore indistinguishable from the list alone.
`headers->has()` would separate them, but it changes no answer, so the list is read
directly and the rule is:

```php
if (! in_array('*', $requestEncodings, true) && ! in_array($compressionAlgorithm, $requestEncodings, true)) {
    return false;
}
```

## The alternative this replaced

The code used to accept an empty list as "accepts anything":

```php
$requestHasThisEncoding = count($requestEncodings) === 0
    || in_array('*', $requestEncodings)
    || in_array($compressionAlgorithm, $requestEncodings, true);
```

That compresses for a client that asked for nothing — a health check, a bare `curl`, a
proxy that strips the header. (`Accept-Encoding: identity` was already refused: it is a
non-empty list that simply fails to match. What the old rule caught was the genuinely
header-less request.)

The evidence that it was wrong was already in the suite. Two tests asserted the
opposite — `it should not compress response without gzip header` and `it should not
compress json response without encoding header` — and they passed only because
`enabled_for_testing` defaulted to `false`, so nothing compressed anywhere and every
assertion about an uncompressed body held for the wrong reason. Repairing that switch is
what turned six previously-"passing" tests red and made this decision visible. See
[enabled-for-testing.md](enabled-for-testing.md).

## Rejected for the same reason

- **Defaulting to `gzip, deflate`**, as HTTP once allowed for a missing header. It
  re-introduces compression for clients that did not ask, and gzip is not the only
  algorithm this package can produce.
- **Treating an empty list as `*`.** The same bug with a shorter spelling.

## What is still accepted

`*` — covered by `it should compress for a client that accepts any encoding`. A client
that names specific encodings still gets only those, and a client that names none gets
the plain body with no `Content-Encoding`.
