package io.github.arshamkhakpour1391.pingfix.client.mixin;

import io.github.arshamkhakpour1391.pingfix.client.HotbarSyncRuntime;
import io.github.arshamkhakpour1391.pingfix.sync.HotbarSelectionState;
import net.minecraft.client.network.ClientPlayerInteractionManager;
import net.minecraft.item.ItemStack;
import org.spongepowered.asm.mixin.Mixin;
import org.spongepowered.asm.mixin.Shadow;
import org.spongepowered.asm.mixin.injection.At;
import org.spongepowered.asm.mixin.injection.Inject;
import org.spongepowered.asm.mixin.injection.callback.CallbackInfo;

/**
 * Separates the interaction manager's send-side slot cursor from its selected-tool snapshot.
 * They must not be treated as the same state under bidirectional network latency.
 */
@Mixin(ClientPlayerInteractionManager.class)
public abstract class ClientPlayerInteractionManagerMixin implements SelectedSlotCacheAccess {
    @Shadow
    private int lastSelectedSlot;

    @Shadow
    private ItemStack selectedStack;

    @Inject(method = "syncSelectedSlot", at = @At("HEAD"))
    private void pingfix$observeVanillaSyncBarrier(CallbackInfo ci) {
        HotbarSyncRuntime.getInstance().onVanillaSyncBarrier((ClientPlayerInteractionManager) (Object) this);
    }

    @Override
    public void pingfix$invalidateSelectedSlotCache() {
        // -1 is outside vanilla's valid [0, 8] range and forces the next normal vanilla sync.
        lastSelectedSlot = HotbarSelectionState.NO_SLOT;
        refreshSelectedStack();
    }

    @Override
    public void pingfix$refreshSelectedStack() {
        // Do not retain an ItemStack reference across a correction or player/world transition.
        // The next vanilla break tick compares the actual held stack and restarts safely if needed.
        selectedStack = ItemStack.EMPTY;
    }
}
