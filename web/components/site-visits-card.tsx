"use client";
import dynamic from "next/dynamic";
import { useTheme } from "next-themes";
import type { ApexOptions } from "apexcharts";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { themes } from "@/config/thems";
import { useThemeStore } from "@/store";
import { useApiQuery, formatDate, type SiteAnalytics } from "@/lib/api";

// ApexCharts touches `window` when it loads, so it is only ever loaded in the browser.
const Chart = dynamic(() => import("react-apexcharts"), { ssr: false });

const DAYS = 30;

/** Page views on the public website, per day and per path. Its own query: a failure here leaves the rest of the dashboard alone. */
const SiteVisitsCard = () => {
  const { data, isLoading, error } = useApiQuery<SiteAnalytics>(["analytics", DAYS], `/admin/analytics?days=${DAYS}`, {
    refetchInterval: 5 * 60_000,
    retry: 1,
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Website visits, last {DAYS} days</CardTitle>
        <CardDescription>Page views on the public website, counted per day and page. Nothing about the visitor is kept.</CardDescription>
      </CardHeader>
      <CardContent>
        {error ? (
          <div className="text-sm text-default-500">Visits could not be loaded: {error.message}</div>
        ) : isLoading || !data ? (
          <div className="text-sm text-default-600">Loading…</div>
        ) : (
          <Visits data={data} />
        )}
      </CardContent>
    </Card>
  );
};

const Visits = ({ data }: { data: SiteAnalytics }) => {
  const days = data.days ?? [];
  const top = data.top ?? [];
  const topMax = Math.max(1, ...top.map((row) => row.views));

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <div className="lg:col-span-2 min-w-0">
        <div className="text-3xl font-semibold text-default-900">{data.total.toLocaleString("en-GB")}</div>
        <div className="text-sm text-default-600">page views</div>
        {data.total > 0 && days.length > 0 ? (
          <>
            <DailyChart days={days} />
            <details className="mt-2 text-sm">
              <summary className="cursor-pointer text-default-500">Daily numbers</summary>
              <table className="mt-2 w-full max-w-xs">
                <tbody>
                  {days.map((d) => (
                    <tr key={d.day}>
                      <td className="py-0.5 text-default-600">{formatDate(d.day)}</td>
                      <td className="py-0.5 text-right tabular-nums text-default-800">{d.views}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </details>
          </>
        ) : (
          <div className="mt-4 text-sm text-default-500">No visits recorded yet. Views appear here a day at a time once the website is live.</div>
        )}
      </div>

      <div className="min-w-0">
        <div className="text-sm font-medium text-default-800 mb-3">Most viewed pages</div>
        {top.length === 0 ? (
          <div className="text-sm text-default-500">None yet.</div>
        ) : (
          <ul className="space-y-3">
            {top.map((row) => (
              <li key={row.path}>
                <div className="flex justify-between gap-3 text-sm">
                  <span className="font-mono text-default-800 truncate" title={row.path}>{row.path}</span>
                  <span className="text-default-600 tabular-nums">{row.views.toLocaleString("en-GB")}</span>
                </div>
                <div className="mt-1 h-2 rounded bg-default-100 overflow-hidden">
                  <div className="h-full bg-primary" style={{ width: `${Math.round((row.views / topMax) * 100)}%` }} />
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

const DailyChart = ({ days }: { days: SiteAnalytics["days"] }) => {
  const { theme: config } = useThemeStore();
  const { theme: mode } = useTheme();
  const palette = themes.find((t) => t.name === config)?.cssVars[mode === "dark" ? "dark" : "light"];
  const max = Math.max(...days.map((d) => d.views));

  const series = [{ name: "Page views", data: days.map((d) => ({ x: new Date(`${d.day}T00:00:00`).getTime(), y: d.views })) }];

  const options: ApexOptions = {
    chart: { toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, fontFamily: "inherit" },
    colors: [palette ? `hsl(${palette.primary})` : "#2563eb"],
    plotOptions: { bar: { columnWidth: "70%", borderRadius: 4, borderRadiusApplication: "end" } },
    dataLabels: { enabled: false },
    legend: { show: false },
    grid: { borderColor: palette ? `hsl(${palette.chartGird})` : "#e5e7eb", strokeDashArray: 10, position: "back", xaxis: { lines: { show: false } } },
    xaxis: {
      type: "datetime",
      labels: { datetimeUTC: false, style: { colors: palette ? `hsl(${palette.chartLabel})` : undefined } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      min: 0,
      // Whole views only: a small count must not get ticks at 0.5.
      tickAmount: Math.min(4, Math.max(1, max)),
      forceNiceScale: true,
      labels: { formatter: (v: number) => String(Math.round(v)), style: { colors: palette ? `hsl(${palette.chartLabel})` : undefined } },
    },
    tooltip: { theme: mode === "dark" ? "dark" : "light", x: { format: "d MMM yyyy" }, y: { formatter: (v: number) => `${v} views` } },
    states: { active: { filter: { type: "none" } } },
  };

  return (
    <div className="mt-4" aria-label="Page views per day" role="img">
      <Chart options={options} series={series} type="bar" height={200} width="100%" />
    </div>
  );
};

export default SiteVisitsCard;
