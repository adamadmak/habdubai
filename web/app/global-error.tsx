"use client";

/**
 * The last resort: the admin's own frame failed, so nothing of it (styles, sign-in,
 * menus) can be relied on here. Plain, with inline styles on purpose, and one way out.
 */
export default function GlobalError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <html lang="en">
      <body style={{ margin: 0, fontFamily: "system-ui, sans-serif", background: "#f7f8fa", color: "#1f2937" }}>
        <main style={{ maxWidth: "32rem", margin: "6rem auto", padding: "0 1rem", textAlign: "center" }}>
          <h1 style={{ fontSize: "1.5rem", margin: 0 }}>The admin ran into a problem</h1>
          <p style={{ marginTop: "0.75rem", lineHeight: 1.5 }}>Nothing was changed. Try again; if it keeps happening, check that the API is up.</p>
          {error.digest && <p style={{ marginTop: "0.5rem", fontSize: "0.75rem", fontFamily: "monospace" }}>Reference {error.digest}</p>}
          <button type="button" onClick={reset} style={{ marginTop: "1.5rem", padding: "0.625rem 1.25rem", borderRadius: "0.5rem", border: 0, background: "#2563eb", color: "#fff", fontSize: "1rem", cursor: "pointer" }}>
            Try again
          </button>
        </main>
      </body>
    </html>
  );
}
