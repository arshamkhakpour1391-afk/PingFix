package io.github.arshamkhakpour1391.pingfix.client.mixin;

import io.github.arshamkhakpour1391.pingfix.client.HotbarSyncRuntime;
import net.minecraft.client.network.ClientPlayNetworkHandler;
import net.minecraft.network.packet.s2c.play.SetPlayerInventoryS2CPacket;
import net.minecraft.network.packet.s2c.play.UpdateSelectedSlotS2CPacket;
import org.spongepowered.asm.mixin.Mixin;
import org.spongepowered.asm.mixin.injection.At;
import org.spongepowered.asm.mixin.injection.Inject;
import org.spongepowered.asm.mixin.injection.callback.CallbackInfo;

/** Applies authoritative held-slot and held-stack updates only after vanilla packet handling. */
@Mixin(ClientPlayNetworkHandler.class)
public abstract class ClientPlayNetworkHandlerMixin {
    @Inject(method = "onUpdateSelectedSlot", at = @At("TAIL"))
    private void pingfix$reconcileSelectedSlot(UpdateSelectedSlotS2CPacket packet, CallbackInfo ci) {
        HotbarSyncRuntime.getInstance().onAuthoritativeSlot((ClientPlayNetworkHandler) (Object) this, packet.slot());
    }

    @Inject(method = "onSetPlayerInventory", at = @At("TAIL"))
    private void pingfix$refreshCorrectedHeldStack(SetPlayerInventoryS2CPacket packet, CallbackInfo ci) {
        HotbarSyncRuntime.getInstance().onAuthoritativeInventorySlot(
                (ClientPlayNetworkHandler) (Object) this,
                packet.slot()
        );
    }
}
