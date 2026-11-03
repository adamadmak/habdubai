/**
 * The Laravel API, called from this website's server only. Public reads are
 * cached for a minute (the API caches too); anything with an owner's token
 * or a write is never cached.
 */
import type { Locale } from "./i18n";

const API = (process.env.API_URL || "http://localhost:8000/api").replace(/\/+$/, "");

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

/** Proves to the API that a call comes from this website, so it believes the visitor address sent with it. */
const SITE_KEY = process.env.SITE_API_KEY ?? "";

type Options = {
  revalidate?: number;
  token?: string | null;
  method?: string;
  body?: unknown;
  /** The visitor, for calls made on their behalf (never on cached reads: it would split the cache per visitor). */
  client?: { ip: string | null; agent: string | null };
};

export async function api<T>(path: string, options: Options = {}): Promise<T> {
  const method = options.method ?? "GET";
  const headers: Record<string, string> = { Accept: "application/json" };
  if (options.token) headers.Authorization = `Bearer ${options.token}`;
  if (SITE_KEY) headers["X-Site-Key"] = SITE_KEY;
  if (options.client?.ip) headers["X-Client-Ip"] = options.client.ip;
  if (options.client?.agent) headers["X-Client-Agent"] = options.client.agent;

  let body: string | undefined;
  if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  const cacheable = method === "GET" && !options.token && !options.client;
  const response = await fetch(`${API}${path}`, {
    method,
    headers,
    body,
    ...(cacheable ? { next: { revalidate: options.revalidate ?? 60 } } : { cache: "no-store" as const }),
  });

  if (!response.ok) {
    let data: { message?: string; errors?: Record<string, string[]> } = {};
    try {
      data = await response.json();
    } catch {
      // Not JSON: keep the status text.
    }
    throw new ApiError(response.status, data.message ?? response.statusText, data.errors ?? {});
  }

  return (await response.json()) as T;
}

/** The same, but a 404 comes back as null so a page can call notFound(). */
export async function apiOrNull<T>(path: string, options: Options = {}): Promise<T | null> {
  try {
    return await api<T>(path, options);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}

export function query(params: Record<string, string | number | undefined | null>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== "") search.set(key, String(value));
  }
  const text = search.toString();
  return text ? `?${text}` : "";
}

// ---- shapes --------------------------------------------------------------------

export type Labels = Partial<Record<Locale, string>>;
export type LabelList = Record<string, Labels>;

export interface Meta {
  bot: { username: string | null; link: string | null };
  channel: string | null;
  questions_group: string | null;
  goods_group: string | null;
  support: string | null;
  labels: { trades: LabelList; emirates: LabelList; business_categories: LabelList; languages: LabelList };
}

export interface Page<T> {
  data: T[];
  meta: { total: number; page: number; last_page: number };
}

export interface Job {
  id: number;
  title: string;
  company: string | null;
  category: string;
  emirate: string;
  area: string | null;
  salary: string | null;
  accommodation: boolean;
  visa: boolean;
  source: string | null;
  open: boolean;
  published_at: string | null;
  summary: Record<Locale, string>;
  apply_url?: string | null;
  languages?: string[];
  warning?: string[];
}

export interface City {
  key: string;
  name: Labels;
}

export interface Business {
  id: number;
  slug: string;
  name: string;
  category: string;
  city: City | null;
  area: string;
  description: string | null;
  verified: boolean;
  owner_managed: boolean;
  photo: string | null;
  phone: string | null;
  whatsapp: string | null;
  telegram: string | null;
  about?: string | null;
  website?: string | null;
  hours?: string | null;
  languages?: string[];
  map_link?: string;
  latitude?: number | null;
  longitude?: number | null;
  updated_at?: string | null;
}

export interface Topic {
  key: string;
  emoji: string;
  name: Labels;
}

export interface Announcement {
  id: number;
  topic: Topic | null;
  text: Record<Locale, string>;
  original_language: Locale;
  event_date: string | null;
  event_place: string | null;
  photo: string | null;
  published_at: string | null;
}

export interface Home {
  stats: { members: number; jobs: number; businesses: number; goods: number };
  jobs: Job[];
  businesses: Business[];
  events: Announcement[];
  announcements: Announcement[];
  categories: { key: string; name: Labels; count: number }[];
  meta: Meta;
}

export interface Facets {
  cities: (City & { count: number })[];
  categories: { key: string; name: Labels; count: number }[];
  languages: LabelList;
}

// ---- owners ------------------------------------------------------------------------

export interface Owner {
  id: number;
  name: string;
  username: string | null;
  language: Locale;
  member: boolean;
}

export type ListingStatus = "pending" | "approved" | "rejected" | "hidden";

export interface OwnerBusiness {
  id: number;
  slug: string | null;
  status: ListingStatus;
  review_note: string | null;
  name: string;
  category: string;
  city_id: number;
  city: Labels | null;
  area: string;
  maps_url: string | null;
  phone: string | null;
  whatsapp: string | null;
  telegram: string | null;
  website: string | null;
  description: string | null;
  about: string | null;
  hours: string | null;
  languages: string[];
  verified: boolean;
  photo: string | null;
  pending_photo: string | null;
  pending_changes: Record<string, unknown> | null;
  changes_in_review: boolean;
  views: number;
  submitted_at: string | null;
  updated_at: string | null;
}

export interface OwnerClaim {
  id: number;
  status: "pending" | "approved" | "rejected";
  note: string | null;
  review_note: string | null;
  business: { id: number; name: string; slug: string | null } | null;
  created_at: string | null;
}

export interface OwnerChoices {
  categories: LabelList;
  cities: { id: number; key: string; name: Labels }[];
  languages: LabelList;
  limits: { listings: number; pending: number; photo_kb: number };
}
