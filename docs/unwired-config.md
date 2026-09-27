# What should a published config key that nothing reads do?

**Stay published, and say plainly that nothing reads it.** `enable_logging` is inert and
its docblock in both the config file and the README says so.

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
config file and in the README, so that reading either one tells the truth.

## What wiring it would take

1. Add `illuminate/log` to `composer.json`'s `require` block — an app always has it, but
   this package no longer declares the dependency the facade needs.
2. Restore a logging call behind the flag, deciding alongside it whether the message is
   per response or only per compression, since the middleware runs on every API response
   and the volume is the reason the default is `false`.
3. Cover it with a test that asserts a line is written when the flag is on — `Log::spy()`
   in a Testbench test — so the key cannot go inert again unnoticed.

The README's description of the key is deliberately written as "NOT YET WIRED" rather
than as behaviour, and the [config-doc test](../tests/Unit/Config/ConfigDocTest.php)
holds the README to the key and its default without making any claim about whether it
works.
