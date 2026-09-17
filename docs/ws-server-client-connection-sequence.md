# WS Server Client Connection Sequence Numbers

## Problem: `connResourceId` can be recycled by PHP

Ratchet identifies every client connection by a `resourceId`, which is derived directly from the
underlying PHP stream resource: `(int) $conn->stream` (see
`vendor/cboden/ratchet/src/Ratchet/Server/IoServer.php`).

PHP resource ids are just integers handed out by the Zend engine. Once a resource is freed (a
client disconnects and its stream is garbage collected), that integer **can be reused** for a
completely unrelated, brand-new resource/connection. On a short-lived script this is a non-issue,
but the ws-server is a long-running process that can see many thousands of client
connects/disconnects over its lifetime — so id recycling is not just theoretical, it *will*
happen.

This matters because several ws-server plugins (e.g. `LatestWsServerPlugin`,
`ExecuteBatchesWsServerPlugin`) do **asynchronous** work per client: they read the client's info
(`team_id`, `user`, `last_update_time`, ...) for a given `connResourceId`, kick off one or more
async DB queries, and only later — once those queries resolve — act on the result (e.g. decide
whether/what to send back). Between "started the async work" and "the async work resolved",
arbitrary numbers of `onOpen`/`onClose` events can occur, including:

- the original client disconnecting, and
- a **different** client connecting and being handed the very same (now recycled) `resourceId`.

A check like `getClientConnection($connResourceId) !== null` only tells you *some* connection is
currently occupying that slot — not that it's still the *same* connection the async work was
started for. That's the "client resource id reuse hazard".

## Mechanism: a monotonic, never-reused connection sequence number

`WsServer` maintains:

```php
private array $connectionSequences = [];
private int $nextConnectionSequence = 1;
```

- On `onOpen()`, once a connection is accepted, it is assigned the next sequence number
  (`$this->connectionSequences[$conn->resourceId] = $this->nextConnectionSequence++;`).
- On `onClose()`, that connection's entry is removed
  (`unset($this->connectionSequences[$conn->resourceId]);`).
- `getClientConnectionSequence(int $connResourceId): ?int` (see
  `ClientConnectionResourceManagerInterface`) exposes the current sequence number for a resource
  id, or `null` if nothing is currently connected on it.

Unlike `$connResourceId` itself, the sequence number is **never reused**: it strictly increases
for the lifetime of the whole ws-server process. So two different connection instances can never
share the same sequence number, even if they share the same recycled `resourceId`.

### How plugins use it

Any plugin doing async work per client should:

1. Capture `getClientConnectionSequence($connResourceId)` **before** starting the async work.
2. Compare it against `getClientConnectionSequence($connResourceId)` again **after** the async
   work resolves, before acting on/sending the result.
3. If the two values differ (including if either is `null`), treat it exactly like a disconnect
   (e.g. throw `ClientDisconnectedException`) and discard the result — do **not** send it,
   and do **not** attribute it to whatever connection currently occupies that resource id.

`LatestWsServerPlugin::latestForClient()` implements this pattern.

## Concrete case 1: `connResourceId` recycled by a different client

1. `t=0.000` — Client **A** is connected on `resourceId = 77` with `last_update_time = 500.0`.
   The "latest" plugin's periodic tick starts `latestForClient(77, clientInfoA)`, capturing
   `connectionSequence = 42`.
2. `t=0.050` — Client A disconnects. `onClose` removes `clients[77]`, `clientInfoContainer[77]`,
   `clientHeaders[77]` and `connectionSequences[77]`. A's async DB queries are still in flight —
   nothing cancels them.
3. `t=0.060` — A brand-new client **B** connects and, because the old stream resource was just
   freed, PHP hands out the same recycled `resourceId = 77` for B's socket. `onOpen` assigns a
   new sequence number, `connectionSequences[77] = 43`.
4. `t=0.070` — B sends its handshake message (`team_id`, `user`, `last_update_time = 0.0`),
   populating `clientInfoContainer[77]` with B's info.
5. `t=0.150` — A's stale async work resolves. Its closure captured `connectionSequence = 42`.
   `getClientConnectionSequence(77)` now returns `43`. `42 !== 43` → mismatch detected
   unconditionally → `ClientDisconnectedException` is thrown, A's stale payload is discarded.
   **Nothing is sent to B using A's data.**

   Without the sequence check, the only remaining guard would have been an incidental
   `last_update_time` comparison — which only *happens* to catch this if A's and B's
   `last_update_time` values differ. If they had coincided (e.g. both `0.0`), A's stale payload
   would have been delivered to B and B's tracked `last_update_time`/`prev_payload` would have
   been silently corrupted with A's data.
6. `t=0.200` — The next regular tick picks up `resourceId = 77` again, now correctly recognizing
   B's own info, computes B's real "latest" payload, and delivers it normally.

## Concrete case 2: the same client disconnects and immediately reconnects

1. `t=0.000` — Client A is connected on `resourceId = 77`, `last_update_time = 500.0`.
   `latestForClient(77, clientInfoA)` captures `connectionSequence = 42`. Async DB work begins.
2. `t=0.050` — A's connection drops (network blip, tab refresh, etc.). `onClose` clears all
   per-connection state for `resourceId = 77`, including `connectionSequences[77]`.
3. `t=0.060` — A's client-side auto-reconnect logic immediately opens a new connection, which
   again happens to be handed the same recycled `resourceId = 77`. `onOpen` assigns a *new*
   sequence number: `connectionSequences[77] = 43`. Note that it does not matter that it is "the
   same user" reconnecting — a new socket always gets a new, never-reused sequence number.
4. `t=0.070` — The reconnected client sends its handshake message again, populating
   `clientInfoContainer[77]` with its (possibly updated) info.
5. `t=0.150` — The original stale async work resolves, captured sequence `42` no longer matches
   the current `43` → `ClientDisconnectedException` → stale payload discarded.
6. `t=0.200` — The next regular tick picks up `resourceId = 77` fresh (sequence `43`), computes a
   brand-new "latest" payload against the reconnected client's *current* state, and sends it.

This is the desired behaviour, not a regression: the discarded payload was computed against a
socket that no longer exists by the time it resolved, and could otherwise have overwritten the
reconnected client's freshly-tracked `last_update_time`/`prev_payload` with stale data — causing
missed updates or wrongly-suppressed diffs afterwards. The worst-case cost is one skipped tick
(~`minIntervalSec`) before the reconnected client is serviced correctly.

## Related fix: orphaned `Deferred`s

Independently of the connection-sequence mechanism, `GameLatest::getLatestEnergy()` and
`GameLatest::getLatestKpi()` used to wrap an inner query's promise in a `Deferred` without wiring
up the rejection path. A rejection from the inner query (e.g. a transient DB error) would leave
that `Deferred` pending forever, silently stalling the entire batched "latest" update loop for
*every* connected client, with no error and no crash. Both now reject their `Deferred` on failure
so the promise always settles.
