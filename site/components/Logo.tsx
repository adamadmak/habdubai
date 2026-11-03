/** The mark: a tibeb diamond, gold around Nile blue, with a thread of light on its edge. */
export function Mark({ className = "size-8" }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
      <defs>
        <linearGradient id="mark-sky" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#2a5cf0" />
          <stop offset="1" stopColor="#1336a6" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="10" fill="url(#mark-sky)" />
      <path d="M16 5.5 26.5 16 16 26.5 5.5 16Z" fill="#f2b705" />
      <path d="M16 10 22 16 16 22 10 16Z" fill="#1b4ddb" />
      <path d="M16 13.6 18.4 16 16 18.4 13.6 16Z" fill="#fff" />
      <rect x="0.5" y="0.5" width="31" height="31" rx="9.5" fill="none" stroke="#fff" strokeOpacity="0.18" />
    </svg>
  );
}

export function Tibeb({ thin = false, className = "" }: { thin?: boolean; className?: string }) {
  return <div aria-hidden="true" className={`${thin ? "tibeb-thin" : "tibeb"} ${className}`} />;
}
