package io.github.arshamkhakpour1391.pingfix.sync;

/**
 * Allocation-free state for the client-side selected-hotbar-slot reconciliation path.
 *
 * <p>This class intentionally has no Minecraft dependencies. The runtime calls it only
 * from the client thread; keeping the transition rules here makes the packet-facing code
 * small, deterministic, and independently testable.</p>
 */
public final class HotbarSelectionState {
    public static final int NO_SLOT = -1;
    public static final int HOTBAR_SIZE = 9;

    private boolean active;
    private long session;
    private long revision;
    private int localSlot = NO_SLOT;
    private int authoritativeSlot = NO_SLOT;
    private int lastSyncBarrierSlot = NO_SLOT;

    public static boolean isValidSlot(int slot) {
        return slot >= 0 && slot < HOTBAR_SIZE;
    }

    /** Starts a new world/connection/player session. Invalid input is ignored safely. */
    public boolean beginSession(int slot) {
        if (!isValidSlot(slot)) {
            return false;
        }

        active = true;
        session++;
        revision++;
        localSlot = slot;
        authoritativeSlot = slot;
        lastSyncBarrierSlot = NO_SLOT;
        return true;
    }

    /** Records the local input state after vanilla has selected a hotbar slot. */
    public boolean observeLocalSlot(int slot) {
        if (!isValidSlot(slot)) {
            return false;
        }
        if (!active) {
            return beginSession(slot);
        }
        if (localSlot == slot) {
            return false;
        }

        localSlot = slot;
        revision++;
        return true;
    }

    /**
     * Records the selected-slot synchronization barrier placed by vanilla before an action.
     * It does not create, delay, suppress, or reorder packets.
     */
    public boolean observeVanillaSyncBarrier(int slot) {
        if (!observeLocalSlot(slot)) {
            if (!active || localSlot != slot) {
                return false;
            }
        }
        lastSyncBarrierSlot = slot;
        return true;
    }

    /** Accepts a validated authoritative server correction. */
    public boolean observeAuthoritativeSlot(int slot) {
        if (!isValidSlot(slot)) {
            return false;
        }
        if (!active) {
            return beginSession(slot);
        }

        boolean changed = localSlot != slot || authoritativeSlot != slot;
        localSlot = slot;
        authoritativeSlot = slot;
        lastSyncBarrierSlot = slot;
        if (changed) {
            revision++;
        }
        return changed;
    }

    /** Clears every value that could otherwise survive a disconnect or world replacement. */
    public void endSession() {
        active = false;
        localSlot = NO_SLOT;
        authoritativeSlot = NO_SLOT;
        lastSyncBarrierSlot = NO_SLOT;
    }

    public boolean isActive() {
        return active;
    }

    public long session() {
        return session;
    }

    public long revision() {
        return revision;
    }

    public int localSlot() {
        return localSlot;
    }

    public int authoritativeSlot() {
        return authoritativeSlot;
    }

    public int lastSyncBarrierSlot() {
        return lastSyncBarrierSlot;
    }
}
