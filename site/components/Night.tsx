import type { ReactNode } from "react";
import { Tibeb } from "./Logo";

/*
 * The horizon at the foot of every night band: the highlands of home on the
 * left (flat-topped ambas, an acacia), running into the skyline of the
 * Emirates on the right, with a few windows still lit. One drawing, so the
 * two homes read as one view. Decoration only.
 */
function Horizon({ low = false }: { low?: boolean }) {
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

  return (
    <svg
      viewBox="0 0 1440 166"
      preserveAspectRatio="xMidYMax slice"
      aria-hidden="true"
      className={`block w-full ${low ? "h-14 md:h-20" : "h-24 md:h-36"}`}
    >
      {/* The far highlands, fading out to the flat coast. */}
      <path
        d="M0 166V128l40-4 30-18 50-4 18 10 58-2 28-24 76-4 18 16 54 2 32-24 82-4 22 20 58 4 38 12 76 4 80 8 100 6 140 4 160-2 140 4 140-2V166Z"
        fill="#1a2c78"
        opacity="0.55"
      />
      <g fill="#081130">
        {/* Rolling hills, then the ground under the city. */}
        <path d="M0 166v-10c90-16 170-18 250-6s170 10 250 2 160-2 240 10l700 4v0Z" />
        {/* Two acacias on the hills. */}
        <path d="M340 132c10-12 52-14 64-2-12 4-52 5-64 2Z" />
        <path d="M368 156l-1.6-20-6-6 1.8-1 5.4 5 7-8 1.8 1-7.6 9 2.4 20Z" />
        <path d="M458 140c7-8 36-9 44-1-8 3-36 3-44 1Z" />
        <path d="M478 154l-1-13-4-4 1.2-.8 3.6 3.2 4.8-5 1.2.8-5 6 1.6 13Z" />
        {towers.map(([x, top, w]) => <rect key={`${x}-${top}`} x={x} y={top} width={w} height={166 - top} />)}
        {/* The spire's needle and the sail. */}
        <path d="M1021 26 1022 0 1023 26Z" />
        <path d="M1160 166V78q54 22 52 88Z" />
        <rect x="1159" y="62" width="2" height="18" />
        <rect x="898" y="96" width="2" height="18" />
      </g>
      <g fill="#f2b705" opacity="0.75">
        {windows.map(([x, y]) => <rect key={`${x}-${y}`} x={x} y={y} width="2.6" height="3.2" rx="0.4" />)}
      </g>
    </svg>
  );
}

/**
 * A night band: the hero of the front page, and the head of every section
 * page. It reaches up under the floating header; the tibeb closes it.
 */
export function Night({ children, word, low = false, className = "" }: { children: ReactNode; word?: string; low?: boolean; className?: string }) {
  return (
    <section className={`night under-header ${className}`}>
      {word && (
        <span aria-hidden="true" className="sky-word absolute -right-6 top-[calc(var(--header-space)+1rem)] hidden text-[11rem] md:block lg:text-[15rem]">
          {word}
        </span>
      )}
      <div className="relative">{children}</div>
      <Horizon low={low} />
      <Tibeb />
    </section>
  );
}
