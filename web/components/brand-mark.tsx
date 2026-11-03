import { useId } from "react";

/**
 * The Habesha Dubai mark: a tibeb diamond, gold around Nile blue, on a blue
 * tile. Copied from the website (site/components/Logo.tsx) so both read the same.
 */
const BrandMark = ({ className = "h-8 w-8", title }: { className?: string; title?: string }) => {
  // Each copy on a page needs its own gradient id.
  const id = `mark-sky-${useId().replace(/:/g, "")}`;
  return (
    <svg viewBox="0 0 32 32" className={className} role={title ? "img" : undefined} aria-hidden={title ? undefined : true} aria-label={title}>
      <defs>
        <linearGradient id={id} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#2a5cf0" />
          <stop offset="1" stopColor="#1336a6" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="10" fill={`url(#${id})`} />
      <path d="M16 5.5 26.5 16 16 26.5 5.5 16Z" fill="#f2b705" />
      <path d="M16 10 22 16 16 22 10 16Z" fill="#1b4ddb" />
      <path d="M16 13.6 18.4 16 16 18.4 13.6 16Z" fill="#fff" />
      <rect x="0.5" y="0.5" width="31" height="31" rx="9.5" fill="none" stroke="#fff" strokeOpacity="0.18" />
    </svg>
  );
};

export default BrandMark;
