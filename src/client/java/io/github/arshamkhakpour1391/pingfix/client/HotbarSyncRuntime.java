package io.github.arshamkhakpour1391.pingfix.client;

import io.github.arshamkhakpour1391.pingfix.client.mixin.SelectedSlotCacheAccess;
import io.github.arshamkhakpour1391.pingfix.sync.HotbarSelectionState;
import net.minecraft.client.MinecraftClient;
import net.minecraft.client.network.ClientPlayerEntity;
import net.minecraft.client.network.ClientPlayNetworkHandler;
import net.minecraft.client.network.ClientPlayerInteractionManager;
import net.minecraft.client.world.ClientWorld;

/**
 * Owns selected-slot reconciliation for one Minecraft client. All entry points execute on
 * the client thread: the Fabric tick callback and vanilla's packet handlers are serialized
 * there. No asynchronous task, timeout, retry loop, or extra packet is introduced.
 */
public final class HotbarSyncRuntime {
    private static final HotbarSyncRuntime INSTANCE = new HotbarSyncRuntime();

    private final HotbarSelectionState state = new HotbarSelectionState();

    // These are identity guards, not long-lived caches. They are cleared on every disconnect.
    private ClientPlayerEntity player;
    private ClientWorld world;
    private ClientPlayNetworkHandler networkHandler;
    private ClientPlayerInteractionManager interactionManager;
    private boolean hasContext;

    private HotbarSyncRuntime() {
    }

    public static HotbarSyncRuntime getInstance() {
        return INSTANCE;
    }

    /**
     * Detects player, world, handler, and interaction-manager replacement without allocating
     * per tick. A replacement invalidates only vanilla's local cache so its next normal
     * synchronization barrier sends the current slot exactly once.
     */
    public void onEndClientTick(MinecraftClient client) {
        if (!client.isOnThread()) {
            return;
        }

        ClientPlayerEntity currentPlayer = client.player;
        ClientWorld currentWorld = client.world;
        ClientPlayNetworkHandler currentHandler = client.getNetworkHandler();
        ClientPlayerInteractionManager currentInteractionManager = client.interactionManager;

        if (currentPlayer == null || currentWorld == null || currentHandler == null || currentInteractionManager == null) {
            clearContext();
            return;
        }

        int selectedSlot = currentPlayer.getInventory().getSelectedSlot();
        if (!HotbarSelectionState.isValidSlot(selectedSlot)) {
            // Never write an invalid client value into vanilla's packet/cache path.
            clearContext();
            return;
        }

        boolean changedContext = !hasContext
                || player != currentPlayer
                || world != currentWorld
                || networkHandler != currentHandler
                || interactionManager != currentInteractionManager;

        if (changedContext) {
            boolean mustResynchronize = hasContext;
            player = currentPlayer;
            world = currentWorld;
            networkHandler = currentHandler;
            interactionManager = currentInteractionManager;
            hasContext = true;
            state.beginSession(selectedSlot);

            if (mustResynchronize) {
                invalidateVanillaCache(currentInteractionManager);
            }
            return;
        }

        state.observeLocalSlot(selectedSlot);
    }

    /** Called at vanilla's own action-ordering barrier, immediately before it may send a slot packet. */
    public void onVanillaSyncBarrier(ClientPlayerInteractionManager source) {
        MinecraftClient client = MinecraftClient.getInstance();
        if (!client.isOnThread() || source != client.interactionManager || client.player == null) {
            return;
        }

        int selectedSlot = client.player.getInventory().getSelectedSlot();
        if (HotbarSelectionState.isValidSlot(selectedSlot)) {
            state.observeVanillaSyncBarrier(selectedSlot);
        }
    }

    /**
     * Called after vanilla has applied UpdateSelectedSlotS2CPacket. The server is authoritative.
     * The send-side slot cursor is deliberately left untouched: a newer C2S slot packet can be
     * in flight when the correction arrives on the opposite network direction, and vanilla must
     * retain that cursor to re-establish convergence on its next normal synchronization tick.
     */
    public void onAuthoritativeSlot(ClientPlayNetworkHandler source, int selectedSlot) {
        if (!HotbarSelectionState.isValidSlot(selectedSlot)) {
            return;
        }

        MinecraftClient client = MinecraftClient.getInstance();
        if (!isCurrentClientHandler(client, source) || client.player == null) {
            return;
        }

        // TAIL injection guarantees vanilla handled the packet first. Do not accept a value that
        // vanilla rejected or that belongs to a replacing player/world context.
        if (client.player.getInventory().getSelectedSlot() != selectedSlot) {
            return;
        }

        state.observeAuthoritativeSlot(selectedSlot);
        // Clear only the held-tool snapshot. Do not overwrite vanilla's send-side cursor;
        // it may describe a newer outbound selection that the server has not processed yet.
        refreshSelectedStack(client.interactionManager, client.player);
    }

    /** Refreshes the held-tool snapshot after an authoritative update to a hotbar inventory slot. */
    public void onAuthoritativeInventorySlot(ClientPlayNetworkHandler source, int inventorySlot) {
        if (!HotbarSelectionState.isValidSlot(inventorySlot)) {
            return;
        }

        MinecraftClient client = MinecraftClient.getInstance();
        if (!isCurrentClientHandler(client, source) || client.player == null) {
            return;
        }
        if (client.player.getInventory().getSelectedSlot() != inventorySlot) {
            return;
        }

        refreshSelectedStack(client.interactionManager, client.player);
    }

    /**
     * A screen-handler update has already been applied by vanilla. Its slot index is handler
     * relative, so inferring a player-inventory index here is error-prone for containers and
     * mounts. Invalidating only the transient tool snapshot is safe and avoids a stale held
     * stack after any authoritative inventory revision.
     */
    public void onAuthoritativeInventoryRevision(ClientPlayNetworkHandler source) {
        MinecraftClient client = MinecraftClient.getInstance();
        if (!isCurrentClientHandler(client, source)) {
            return;
        }

        refreshSelectedStack(client.interactionManager, client.player);
    }

    /** Clears stale references as soon as vanilla completes a respawn/dimension replacement. */
    public void onPlayerLifecycleTransition(ClientPlayNetworkHandler source) {
        MinecraftClient client = MinecraftClient.getInstance();
        if (!isCurrentClientHandler(client, source)) {
            return;
        }

        // Do not retain references from the old player/world. The next end-of-tick pass creates
        // a fresh context; invalidating now protects interactions occurring in the transition tick.
        player = null;
        world = null;
        networkHandler = null;
        interactionManager = null;
        hasContext = false;
        state.endSession();
        invalidateVanillaCache(client.interactionManager);
    }

    private boolean isCurrentClientHandler(MinecraftClient client, ClientPlayNetworkHandler source) {
        return client.isOnThread() && client.getNetworkHandler() == source;
    }

    private void refreshSelectedStack(ClientPlayerInteractionManager manager, ClientPlayerEntity currentPlayer) {
        if (manager instanceof SelectedSlotCacheAccess cacheAccess) {
            cacheAccess.pingfix$refreshSelectedStackIfStale(currentPlayer.getMainHandStack());
        }
    }

    private void invalidateVanillaCache(ClientPlayerInteractionManager manager) {
        if (manager instanceof SelectedSlotCacheAccess cacheAccess) {
            cacheAccess.pingfix$invalidateSelectedSlotCache();
        }
    }

    private void clearContext() {
        if (!hasContext && !state.isActive()) {
            return;
        }

        player = null;
        world = null;
        networkHandler = null;
        interactionManager = null;
        hasContext = false;
        state.endSession();
    }
}
