import type { ReactNode } from "react";
import { Tibeb } from "./Logo";

/*
 * The horizon at the foot of a dark band, drawn in one thin line: the highlands
 * of home on the left (flat-topped ambas, two acacias), running into the skyline
 * of the Emirates on the right, a few windows lit in yellow. Decoration only.
 */
function Horizon() {
  // Towers on the skyline: x, top, width. The ground is at y=166.
  const towers: [number, number, number][] = [
    [800, 138, 26], [830, 126, 22], [856, 146, 30], [890, 112, 18], [912, 132, 30], [946, 120, 20],
    [990, 118, 64], [998, 94, 48], [1006, 70, 32], [1013, 48, 18], [1018, 26, 8],
    [1062, 124, 26], [1092, 100, 22], [1118, 136, 28], [1228, 128, 24], [1256, 110, 26],
    [1286, 140, 22], [1312, 118, 30], [1346, 132, 22], [1372, 104, 20], [1396, 138, 44],
  ];
  const windows: [number, number][] = [
    [838, 134], [838, 144], [898, 122], [898, 134], [920, 142], [1010, 102], [1032, 102], [1020, 80], [1036, 124],
    [1100, 110], [1100, 122], [1104, 140], [1264, 120], [1272, 132], [1320, 128], [1328, 140], [1380, 114], [1380, 128],
  ];
  const ink = { fill: "none", stroke: "var(--ink)", strokeWidth: 1.2, strokeLinejoin: "round" as const, strokeLinecap: "round" as const, vectorEffect: "non-scaling-stroke" as const };

  return (
    <svg viewBox="0 0 1440 166" preserveAspectRatio="xMidYMax slice" aria-hidden="true" className="block h-20 w-full md:h-28">
      <path
        {...ink}
        strokeOpacity="0.45"
        d="M0 128l40-4 30-18 50-4 18 10 58-2 28-24 76-4 18 16 54 2 32-24 82-4 22 20 58 4 38 12 76 4 80 8 100 6 140 4 160-2 140 4 140-2"
      />
      <path {...ink} d="M0 156c90-16 170-18 250-6s170 10 250 2 160-2 240 10l700 4" />
      <path {...ink} d="M340 132c10-12 52-14 64-2-12 4-52 5-64 2Z" />
      <path {...ink} d="M368 156l-1.6-20-6-6M366.4 136l7-8M368 156l2.4 20" />
      <path {...ink} d="M458 140c7-8 36-9 44-1-8 3-36 3-44 1Z" />
      <path {...ink} d="M478 154l-1-13-4-4M477 141l4.8-5" />
      {towers.map(([x, top, w]) => <rect key={`${x}-${top}`} {...ink} x={x} y={top} width={w} height={166 - top} />)}
      <path {...ink} d="M1021 26 1022 0 1023 26" />
      <path {...ink} d="M1160 166V78q54 22 52 88" />
      <path {...ink} d="M1160 62v18M899 96v18" />
      <g fill="var(--color-accent)" opacity="0.9">
        {windows.map(([x, y]) => <rect key={`${x}-${y}`} x={x} y={y} width="2.6" height="3.2" rx="0.4" />)}
      </g>
    </svg>
  );
}

// `word` and `low` are older props the pages still pass; the plain bands no longer draw them.
type BandProps = { children: ReactNode; word?: string; low?: boolean; className?: string };

/** The head of a section page: a plain cream band that reaches up under the floating header. */
export function Dawn({ children, className = "" }: BandProps) {
  return (
    <section className={`dawn under-header ${className}`}>
      <div className="relative">{children}</div>
    </section>
  );
}

/** A dark band: the heroes of the front page, the goods board and the owners' landing. */
export function Night({ children, className = "" }: BandProps) {
  return (
    <section className={`night under-header ${className}`}>
      <div className="relative">{children}</div>
      <Horizon />
      <Tibeb />
    </section>
  );
}
