package io.github.arshamkhakpour1391.pingfix.client;

import net.fabricmc.api.ClientModInitializer;
import net.fabricmc.fabric.api.client.event.lifecycle.v1.ClientTickEvents;

/** Client entry point. The mod has no server-side behaviour and sends no custom packets. */
public final class PingFixClient implements ClientModInitializer {
    @Override
    public void onInitializeClient() {
        ClientTickEvents.END_CLIENT_TICK.register(HotbarSyncRuntime.getInstance()::onEndClientTick);
    }
}
