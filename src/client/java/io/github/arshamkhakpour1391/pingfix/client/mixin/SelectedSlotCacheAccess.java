package io.github.arshamkhakpour1391.pingfix.client.mixin;

/** Internal bridge implemented by the interaction-manager mixin. */
public interface SelectedSlotCacheAccess {
    void pingfix$invalidateSelectedSlotCache();

    void pingfix$refreshSelectedStack();
}
