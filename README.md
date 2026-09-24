# PingFix

PingFix is a **client-side, non-cheat Fabric mod for Minecraft Java 1.21.11**.

It hardens the selected-hotbar-slot synchronization path used by scroll input, number keys,
inventory/container interactions, item use, attacks, block breaking, and block placement:

- accepts server `UpdateSelectedSlotS2CPacket` corrections as authoritative;
- keeps vanilla's send-side selection cursor intact after a correction, so a newer packet already
  in flight cannot be falsely acknowledged or lost under bidirectional latency;
- clears the cached selected tool on selected-slot and held-stack corrections so active breaking
  re-evaluates the actual held stack instead of continuing with a stale item;
- invalidates stale local cache only when the player, world, network handler, or interaction
  manager is replaced (disconnect, reconnect, respawn, transfer, or dimension/world transition);
- retains vanilla packet generation and ordering. It adds no custom packets, retries, timers,
  background threads, automation, or gameplay advantage.

The project is pinned to Minecraft **1.21.11**, Yarn **1.21.11+build.4**, Fabric Loader
**0.19.5**, and Fabric API **0.141.6+1.21.11**. Java 21 is required to build and run it.

## Build

```sh
./gradlew clean check build
```

The remapped installable mod JAR is written to `build/libs/PingFix-1.0.0+1.21.11.jar`.
