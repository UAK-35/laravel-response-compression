# Why refuse a client by user-agent prefix?

**Because `Accept-Encoding` is the client's own claim, and some clients claim an
encoding they then cannot decode.** Each algorithm may carry a
`non_supporting_user_agent_prefixes` list; a client matching one of those prefixes is
served the body uncompressed.

## The lists as shipped

| Algorithm | Refused prefixes |
|---|---|
| `gzip` | *(none — the key is absent, so every client is served gzip)* |
| `br` | `ELB-HealthChecker/`, `PostmanRuntime/`, `axios/`, `Dart/` |
| `zstd` | the same four, plus `IntelliJ HTTP Client/` |

## Why a prefix list and not something else

- **Not `Accept-Encoding` alone.** Negotiation is exactly what is broken here: the
  client advertised support it does not have. The README's own warning says as much —
  browsers accept brotli only over HTTPS, so a client that advertises `br` on a
  non-secure connection may fail to decode the result.
- **Prefixes, not full user agents.** Announced versions change constantly
  (`axios/1.7.2`), and what is being identified is a family, not a release. A prefix
  list also fails open: a new version of a known-bad client is still caught.
- **Per algorithm.** The lists differ because the failure modes differ — zstd
  additionally refuses `IntelliJ HTTP Client/` — so a single shared list would refuse
  `br` for a client that handles it, or the reverse.
- **An absent or empty list means "refuse nobody".** `gzip` has no such key, and
  `CompressResponse::nonSupportingUserAgentPrefixes()` returns `[]` for a key that is
  not an array. That is the intended reading: gzip is old enough that a
  non-supporting client is not a case worth carrying.

## Where it is applied

The refusal is the second half of `validateRequestPerAlgoAndUserAgent()`, and it is
reached for the single-algorithm path and for every candidate in
`try_multiple_encodings` — a client refused for `br` can still be served `gzip` by the
next entry in the order.

Covered by `it should not compress for a user agent that cannot decode brotli`.
