/** The mark: a tibeb diamond, gold around Nile blue. */
export function Mark({ className = "size-8" }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
      <rect width="32" height="32" rx="9" fill="var(--color-primary)" />
      <path d="M16 5.5 26.5 16 16 26.5 5.5 16Z" fill="var(--color-accent)" />
      <path d="M16 10 22 16 16 22 10 16Z" fill="var(--color-primary)" />
      <path d="M16 13.6 18.4 16 16 18.4 13.6 16Z" fill="#fff" />
    </svg>
  );
}

export function Tibeb({ thin = false, className = "" }: { thin?: boolean; className?: string }) {
  return <div aria-hidden="true" className={`${thin ? "tibeb-thin" : "tibeb"} ${className}`} />;
}
