"use client";

import { useState } from "react";

export default function Page() {
  const [note, setNote] = useState("");
  const [token, setToken] = useState("");
  const [busy, setBusy] = useState(false);

  async function createLink() {
    setBusy(true);
    setNote("");
    setToken("");
    try {
      const response = await fetch("/api/device", { method: "POST" });
      const data = await response.json();
      if (!response.ok || data.ok === false) {
        throw new Error(data.error || "Could not create computer link.");
      }
      setToken(data.token || "");
      setNote(data.note || "Computer link created.");
    } catch (error) {
      setNote(error.message || "Could not create computer link.");
    } finally {
      setBusy(false);
    }
  }

  async function revokeLinks() {
    setBusy(true);
    setNote("");
    try {
      const response = await fetch("/api/device", { method: "DELETE" });
      const data = await response.json();
      if (!response.ok || data.ok === false) {
        throw new Error(data.error || "Could not revoke links.");
      }
      setToken("");
      setNote("All computer links revoked.");
    } catch (error) {
      setNote(error.message || "Could not revoke links.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main style={{ maxWidth: "34rem", margin: "0 auto", padding: "1.5rem" }}>
      <h1 style={{ fontSize: "1.35rem", margin: "0 0 0.5rem" }}>Paper Desk Sync</h1>
      <p style={{ lineHeight: 1.45, color: "#b7c2ce" }}>
        Fort Knox mode: Cursor sign-in, sealed vault storage, signed computer requests, rate
        limits, and revocable 90-day links. Paper only — nothing goes to a broker.
      </p>
      <ol style={{ lineHeight: 1.5, color: "#b7c2ce", paddingLeft: "1.2rem" }}>
        <li>Sign in with Cursor</li>
        <li>Create a computer link</li>
        <li>
          Put it in <code style={{ color: "#9ad0ff" }}>Documents\Paper Trader\.env</code> as{" "}
          <code style={{ color: "#9ad0ff" }}>PAPER_SYNC_TOKEN=...</code>
        </li>
        <li>Journal → Push book / Pull book</li>
      </ol>
      <div style={{ display: "flex", gap: "0.75rem", flexWrap: "wrap", marginTop: "1rem" }}>
        <button
          type="button"
          disabled={busy}
          onClick={createLink}
          style={{
            padding: "0.55rem 0.9rem",
            borderRadius: "6px",
            border: "0",
            background: "#3d8bfd",
            color: "#fff",
            cursor: "pointer",
          }}
        >
          Create computer link
        </button>
        <button
          type="button"
          disabled={busy}
          onClick={revokeLinks}
          style={{
            padding: "0.55rem 0.9rem",
            borderRadius: "6px",
            border: "1px solid #3a4654",
            background: "transparent",
            color: "#e8eef5",
            cursor: "pointer",
          }}
        >
          Revoke links
        </button>
      </div>
      {token ? (
        <p
          style={{
            marginTop: "1rem",
            padding: "0.75rem",
            background: "#1a222c",
            borderRadius: "6px",
            wordBreak: "break-all",
            fontFamily: "ui-monospace, Consolas, monospace",
            fontSize: "0.85rem",
          }}
        >
          {token}
        </p>
      ) : null}
      {note ? <p style={{ marginTop: "0.75rem", color: "#b7c2ce" }}>{note}</p> : null}
    </main>
  );
}
