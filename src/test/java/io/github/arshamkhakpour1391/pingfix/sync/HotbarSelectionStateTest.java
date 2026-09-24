package io.github.arshamkhakpour1391.pingfix.sync;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class HotbarSelectionStateTest {
    @Test
    void rejectsInvalidSlotsWithoutMutatingState() {
        HotbarSelectionState state = new HotbarSelectionState();

        assertFalse(state.beginSession(-1));
        assertFalse(state.isActive());
        assertFalse(state.observeLocalSlot(9));
        assertEquals(HotbarSelectionState.NO_SLOT, state.localSlot());
    }

    @Test
    void repeatedInputForTheSameSlotDoesNotCreateAStateTransition() {
        HotbarSelectionState state = new HotbarSelectionState();
        assertTrue(state.beginSession(3));
        long revision = state.revision();

        assertFalse(state.observeLocalSlot(3));
        assertEquals(revision, state.revision());
        assertTrue(state.observeVanillaSyncBarrier(3));
        assertEquals(3, state.lastSyncBarrierSlot());
    }

    @Test
    void serverCorrectionSupersedesStaleLocalIntent() {
        HotbarSelectionState state = new HotbarSelectionState();
        state.beginSession(1);
        state.observeLocalSlot(7);
        state.observeVanillaSyncBarrier(7);

        assertTrue(state.observeAuthoritativeSlot(2));
        assertEquals(2, state.localSlot());
        assertEquals(2, state.authoritativeSlot());
        assertEquals(2, state.lastSyncBarrierSlot());
    }

    @Test
    void lateCorrectionDoesNotPreventTheNextLocalSelectionFromBeingObserved() {
        HotbarSelectionState state = new HotbarSelectionState();
        state.beginSession(0);
        state.observeLocalSlot(6);
        state.observeVanillaSyncBarrier(6);

        // Model a server correction arriving while a newer local selection is in flight.
        state.observeAuthoritativeSlot(2);
        long correctionRevision = state.revision();

        assertTrue(state.observeLocalSlot(6));
        assertTrue(state.observeVanillaSyncBarrier(6));
        assertEquals(6, state.localSlot());
        assertEquals(2, state.authoritativeSlot());
        assertEquals(6, state.lastSyncBarrierSlot());
        assertTrue(state.revision() > correctionRevision);
    }

    @Test
    void disconnectClearsEverySlotReferenceAndNextConnectionGetsANewSession() {
        HotbarSelectionState state = new HotbarSelectionState();
        state.beginSession(8);
        long firstSession = state.session();

        state.endSession();
        assertFalse(state.isActive());
        assertEquals(HotbarSelectionState.NO_SLOT, state.localSlot());
        assertEquals(HotbarSelectionState.NO_SLOT, state.authoritativeSlot());
        assertEquals(HotbarSelectionState.NO_SLOT, state.lastSyncBarrierSlot());

        assertTrue(state.beginSession(0));
        assertEquals(firstSession + 1, state.session());
    }
}
