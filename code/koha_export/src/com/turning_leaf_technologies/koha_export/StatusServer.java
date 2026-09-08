package com.turning_leaf_technologies.koha_export;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;
import org.apache.logging.log4j.Logger;
import org.json.JSONObject;

import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Lightweight HTTP server for health/status endpoints.
 * Uses JDK built-in HttpServer — zero external dependencies.
 *
 * GET /health  — liveness probe (always 200)
 * GET /ready   — readiness (503 during init, 200 otherwise)
 * GET /status  — detailed JSON progress
 */
public class StatusServer {
	private final HttpServer server;
	private final AtomicReference<ProcessStatus> status = new AtomicReference<>(new ProcessStatus());

	public StatusServer(int port, Logger logger) throws IOException {
		server = HttpServer.create(new InetSocketAddress(port), 0);
		server.setExecutor(Executors.newFixedThreadPool(2));

		server.createContext("/health", ex -> {
			JSONObject body = new JSONObject().put("status", "UP");
			sendJson(ex, 200, body);
		});

		server.createContext("/ready", ex -> {
			ProcessStatus s = status.get();
			boolean ready = s.state != ProcessState.INITIALIZING;
			sendJson(ex, ready ? 200 : 503, new JSONObject().put("ready", ready).put("state", s.state.name()));
		});

		server.createContext("/status", ex -> sendJson(ex, 200, status.get().toJson()));

		server.start();
		logger.info("Status server listening on port " + port);
	}

	private static void sendJson(HttpExchange ex, int code, JSONObject body) throws IOException {
		byte[] bytes = body.toString(2).getBytes(StandardCharsets.UTF_8);
		ex.getResponseHeaders().set("Content-Type", "application/json");
		ex.sendResponseHeaders(code, bytes.length);
		try (OutputStream os = ex.getResponseBody()) {
			os.write(bytes);
		}
	}

	public void updateStatus(ProcessStatus s) {
		status.set(s);
	}

	public ProcessStatus getStatus() {
		return status.get();
	}

	public void stop() {
		server.stop(0);
	}

	public enum ProcessState {
		INITIALIZING, IDLE, RUNNING, ERROR, SHUTTING_DOWN
	}

	public static class ProcessStatus {
		public ProcessState state = ProcessState.INITIALIZING;
		public long lastRunStarted;
		public long lastRunFinished;
		public int recordsProcessed;
		public int errors;
		public String currentPhase = "";
		public boolean fullUpdate;

		public JSONObject toJson() {
			return new JSONObject()
					.put("state", state.name())
					.put("lastRunStarted", lastRunStarted)
					.put("lastRunFinished", lastRunFinished)
					.put("recordsProcessed", recordsProcessed)
					.put("errors", errors)
					.put("currentPhase", currentPhase)
					.put("fullUpdate", fullUpdate);
		}
	}
}
