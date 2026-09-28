# What should a published config key that nothing reads do?

**Stay published, and say plainly that nothing reads it.** `enable_logging` was inert, its
docblock in both the config file and the README said so, and it has since been wired to the
middleware's debug logging — which is the second half of the same answer: a key that stays
published is one that can be given behaviour later without being broken twice, because removing
it would have been the breaking change.

## How it became inert

The key was added alongside a debug logger, and that logger was already a no-op: its
`Log::debug()` call was commented out while the method and its ~20 call sites — every
`logDebugStatus('… - uri: ' . $requestUri)` — stayed in place, concatenating a message on
every API response to throw it away.

Removing the dead code removed the key's only reader. When Rector's dead-code set is
applied to a private method whose parameters nothing uses
(`RemoveUnusedPrivateMethodParameterRector`), the method's *parameters* are stripped and
its *call sites* lose their arguments — which would have kept a logger that logged
nothing while destroying the messages. Deleting the method and its call sites is the
honest version of the same repair, and it took the now-unused `$requestUri` plumbing with
it.

## Why not just delete the key

It is published in two places: the config file a host app copies with
`vendor:publish`, and the README. A host app may already set

```dotenv
RESPONSE_COMPRESSION_LOGGING=true
```

Removing a published key is a breaking change, and a key that silently does nothing is a
lesser surprise than a key that silently disappears. It is annotated instead, in the
config file and in the README, so that reading either one tells the truth — which is what
made the wiring a change to annotations rather than a reversal of one.

## What it does now

`CompressResponse::enableLogging()` reads it through `Config::boolOr()`, and
`logDebugStatus()` writes a line for each decision the middleware makes once it is on: not
enabled, not allowed for this request, skipped and why (binary or streamed, not successful,
not a string, under the floor), which encoding was used, and the disallowed user agent it
refused. Most of the lines name the request URI.

Off by default is the point rather than a caution: the middleware runs on every API
response, so an on-by-default switch would put a line per response into every host app's
log.

It stays out of `Config::validate()` — see [config-reading.md](config-reading.md) — so a
value that cannot be read costs the response that needed the decision rather than the boot.

## What the wiring did not settle

Four things, listed because a record that stops at "wired" would be as misleading as the
"not yet wired" docblock was:

1. **The comments were the work.** The config file, the README and this record described a key
   that did nothing; all three now describe what it does. Nothing else about the key changed.
2. **`ConfigKeys::UNWIRED` is empty.** The guard that compares the published keys against the
   read ones has nothing left to allow, and an entry it no longer needs is a hole in the rule
   it exists to state.
3. **`illuminate/log` is still not in `require`.** `Log` reaches the facade in
   `illuminate/support`, while the manager behind it and the `log` binding come from
   `illuminate/log` — which a Laravel application has and this package does not declare. The
   *tests* reach it through `laravel/framework`, a dev dependency, so a green suite is not
   evidence that the declaration is in place: the absence would be felt by a consumer outside a
   Laravel application. A package that logs should say so in its own `require`.
4. **A line is tested; a log file is not.** Two tests in the middleware suite turn the flag on
   and off around the same request, spy on `Log`, and assert the exact messages the middleware
   composes for a compressed response — and not one line of any kind when the flag is off. The
   second half is the one that matters most: it is what catches the key going inert a second
   time, unnoticed. What is not asserted is that a driver writes those lines anywhere, because
   `Log` is a facade over whatever the host app bound and the binding is `illuminate/log`'s to
   test.
