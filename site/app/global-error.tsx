"use client";
import "./globals.css";

/**
 * The last resort: the page's own frame failed, so no language or header is
 * known here. Short, in the two languages most readers have, and one way out.
 */
export default function GlobalError({ reset }: { error: Error; reset: () => void }) {
  return (
    <html lang="en">
      <body>
        <main className="mx-auto max-w-xl px-4 py-24 text-center">
          <h1 className="display text-3xl">Something went wrong</h1>
          <p className="mt-3 text-muted">We could not show this page. Please try again in a moment.</p>
          <p lang="am" className="mt-6 font-semibold">የሆነ ችግር ተፈጥሯል</p>
          <p lang="am" className="mt-1 text-muted">ገጹን ማሳየት አልቻልንም። እባክዎ ከትንሽ ጊዜ በኋላ እንደገና ይሞክሩ።</p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <button type="button" onClick={reset} className="btn btn-primary">Try again · እንደገና ሞክር</button>
            {/* A full load on purpose: whatever failed in this frame starts over. */}
            {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
            <a href="/" className="btn btn-quiet">Home · መነሻ</a>
          </div>
        </main>
      </body>
    </html>
  );
}
