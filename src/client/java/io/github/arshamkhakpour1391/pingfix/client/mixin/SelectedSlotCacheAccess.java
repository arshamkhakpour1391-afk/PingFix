package io.github.arshamkhakpour1391.pingfix.client.mixin;

import net.minecraft.item.ItemStack;

/** Internal bridge implemented by the interaction-manager mixin. */
public interface SelectedSlotCacheAccess {
    void pingfix$invalidateSelectedSlotCache();

    void pingfix$refreshSelectedStackIfStale(ItemStack currentMainHandStack);
}
