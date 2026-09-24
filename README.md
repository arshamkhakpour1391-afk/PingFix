# PingFix

**Created by arsham.** PingFix is a client-side, fair-play Fabric mod for **Minecraft Java 1.21.11**.

It makes rapid hotbar and inventory switching more dependable without automating gameplay or
altering server authority. The mod keeps vanilla's selected-slot packet path intact while
hardening stale client cache handling around corrections, inventory updates, and player/world
lifecycle changes.

## Reliability coverage

- rapid number-key, scroll-wheel, and repeated same-slot selection;
- attacks, item use, placement, and block breaking while switching;
- selected-slot corrections and delayed bidirectional network traffic;
- player-inventory, container-slot, and full-inventory server revisions;
- reconnects, respawns, dimension/world transfers, and chunk/loading transitions;
- stale selected-tool cleanup without repeatedly invalidating an unchanged held item.

## Fair-play and server-safety audit

PingFix is deliberately limited to client consistency work:

- **does not create, send, cancel, delay, reorder, replay, or spoof packets;**
- **does not automate input, movement, combat, clicks, placement, or inventory actions;**
- **does not alter reach, attack timing, cooldowns, player movement, game rules, or server data;**
- runs only on the normal client thread, with no timers, worker threads, packet queues, or retry
  loops;
- observes authoritative server packets only after vanilla has handled them and preserves
  vanilla's send-side selected-slot cursor under latency.

It is designed as a normal client-side stability/QoL mod rather than a cheat. Individual servers
can still enforce their own mod policies, so always follow the rules of the server you play on.

## Requirements

- Minecraft Java Edition **1.21.11**
- Fabric Loader **0.19.5 or newer**
- Fabric API compatible with **1.21.11**
- Java **21**

## Build

```sh
./gradlew clean check build
```

The verified remapped mod JAR is emitted in `build/libs/`.
