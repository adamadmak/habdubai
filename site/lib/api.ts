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
    /** Why a 403 refused, where the API says (the goods board: not_member, blocked, max_open). */
    public code: string | null = null,
  ) {
    super(message);
  }
}

/** Proves to the API that a call comes from this website, so it believes the visitor address sent with it. */
const SITE_KEY = process.env.SITE_API_KEY ?? "";

/** How long one attempt may take. A hung API must not hold a page open. */
const TIMEOUT_MS = 8000;

type Options = {
  revalidate?: number;
  token?: string | null;
  method?: string;
  body?: unknown;
  /** The visitor, for calls made on their behalf (never on cached reads: it would split the cache per visitor). */
  client?: { ip: string | null; agent: string | null };
  /** The page's language: the owner API words its validation and refusal messages in it. */
  locale?: Locale;
};

export async function api<T>(path: string, options: Options = {}): Promise<T> {
  const method = options.method ?? "GET";
  const headers: Record<string, string> = { Accept: "application/json" };
  if (options.token) headers.Authorization = `Bearer ${options.token}`;
  if (SITE_KEY) headers["X-Site-Key"] = SITE_KEY;
  if (options.client?.ip) headers["X-Client-Ip"] = options.client.ip;
  if (options.client?.agent) headers["X-Client-Agent"] = options.client.agent;
  if (options.locale) headers["X-Locale"] = options.locale;

  let body: string | undefined;
  if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  const cacheable = method === "GET" && !options.token && !options.client;
  const init: RequestInit = {
    method,
    headers,
    body,
    ...(cacheable ? { next: { revalidate: options.revalidate ?? 60 } } : { cache: "no-store" as const }),
  };
  const attempt = () => fetch(`${API}${path}`, { ...init, signal: AbortSignal.timeout(TIMEOUT_MS) });

  let response: Response;
  try {
    response = await attempt();
  } catch (error) {
    // A dropped connection or a timeout, not an answer. A read is asked once
    // more; a write is not, since the first one may have landed.
    if (method !== "GET") throw error;
    response = await attempt();
  }

  if (!response.ok) {
    let data: { message?: string; errors?: Record<string, string[]>; code?: string } = {};
    try {
      data = await response.json();
    } catch {
      // Not JSON: keep the status text.
    }
    throw new ApiError(response.status, data.message ?? response.statusText, data.errors ?? {}, typeof data.code === "string" ? data.code : null);
  }

  // A count or a sign-out answers with no body at all.
  if (response.status === 204) return undefined as T;

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
  // {am, ti, en} today; `om` once the API has it. Read through pick(), which falls back to English.
  summary: Labels;
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
  /** Members' stars, out of five; null until someone rates it. */
  rating: number | null;
  ratings: number;
  /** Three or more ratings averaging 4.5 or better. */
  top?: boolean;
  about?: string | null;
  website?: string | null;
  hours?: string | null;
  languages?: string[];
  map_link?: string;
  latitude?: number | null;
  longitude?: number | null;
  updated_at?: string | null;
  /** How many gave each number of stars, from five down. On a place's own page. */
  rating_breakdown?: { stars: number; count: number }[];
}

/** What a signed-in visitor may do about a place's stars. */
export interface MyBusinessRating {
  can_rate: boolean;
  blocked_by: "not_member" | "own" | null;
  stars: number | null;
  bot_link: string | null;
}

export interface Topic {
  key: string;
  emoji: string;
  name: Labels;
}

export interface Announcement {
  id: number;
  topic: Topic | null;
  // The same: one text per language, `om` not yet everywhere. Read through pick().
  text: Labels;
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

export type MemberRank = "helper" | "trusted" | "elder";

/** The member's own page (GET /owner/home): who they are here and what they have going on. Null parts mean "not a member". */
export interface OwnerHome {
  owner: Owner;
  member: {
    name: string;
    emirate: string;
    trades: string[];
    language: Locale;
    role: string;
    verified_since: string | null;
    helper_points: number;
    rank: MemberRank | null;
    rating: { avg: number; count: number } | null;
    trips_completed: number;
  } | null;
  goods: { open_posts: number; live_deals: number; completed: number } | null;
  questions: { total: number; open: number; latest: { id: number; title: string; status: string; replies: number; created_at: string | null }[] } | null;
  jobs: { alerts_on: boolean; saved_count: number; saved: { id: number; title: string; company: string | null; emirate: string; open: boolean }[] } | null;
  businesses: { approved: number; pending: number; claims_pending: number };
  links: { bot: string | null; channel: string | null; questions_group: string | null; goods_group: string | null };
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

// ---- the goods board -----------------------------------------------------------------

export type GoodsKind = "traveller" | "sender";
export type GoodsDirection = "to_et" | "to_ae";
/** What a parcel post is for: carry what I have here, or bring me what is over there. Null on a trip. */
export type GoodsMode = "send" | "request";
export type GoodsCurrency = "AED" | "ETB";

export const GOODS_KINDS: readonly GoodsKind[] = ["traveller", "sender"];
export const GOODS_DIRECTIONS: readonly GoodsDirection[] = ["to_et", "to_ae"];
export const GOODS_MODES: readonly GoodsMode[] = ["send", "request"];
export const GOODS_CURRENCIES: readonly GoodsCurrency[] = ["AED", "ETB"];

export interface GoodsMemberBadge {
  name: string;
  /** "2025-03": the month the member proved their phone number to the bot. */
  verified_since: string | null;
  rating: number | null;
  ratings: number;
  /** Three or more ratings averaging 4.5 or better. */
  top?: boolean;
  deals: number;
}

/** One open post on the board: a trip with spare kilos, or a parcel. */
export interface GoodsCard {
  id: number;
  kind: GoodsKind;
  mode: GoodsMode | null;
  direction: GoodsDirection;
  date: string;
  from: { key: string; name: Labels };
  to: { key: string; name: Labels };
  /** The trip's spare kilos in all, or the parcel's weight. */
  kilos: number;
  /** The trip's room still free; null on a parcel. */
  kilos_left: number | null;
  categories: { key: string; name: Labels }[];
  description: string | null;
  /** The carry fee, paid member to member: per kilo on a trip, a total offer on a parcel. 0 is free, null is "to agree". */
  price: number | null;
  currency: GoodsCurrency | null;
  price_basis: "per_kg" | "total" | null;
  member: GoodsMemberBadge;
  created_at: string;
}

export interface GoodsChoices {
  directions: Record<GoodsDirection, Labels>;
  cities: Record<GoodsDirection, { from: LabelList; to: LabelList }>;
  categories: LabelList;
  max_kilos: number;
  kilos_traveller: number[];
  kilos_sender: number[];
  max_days_ahead: number;
  max_open: number;
}

/** A deal lately delivered: the way it went and how it was rated, with no names. */
export interface GoodsDelivered {
  id: number;
  from: { key: string; name: Labels };
  to: { key: string; name: Labels };
  kilos: number;
  delivered_at: string | null;
  stars: number | null;
}

export interface GoodsBoard extends Page<GoodsCard> {
  stats: { trips: number; parcels: number; deals: number; kilos: number };
  recent?: GoodsDelivered[];
  choices: GoodsChoices;
}

export type GoodsPostStatus = "open" | "matched" | "expired" | "closed" | "hidden";

export type GoodsOutcome = "delivered" | "failed" | "unconfirmed" | "cancelled" | "member";

export type MyGoodsPost = GoodsCard & { status: GoodsPostStatus; outcome: GoodsOutcome | null; open: boolean; requests: number };

export interface GoodsDeal {
  id: number;
  status: string;
  role: "owner" | "requester";
  other_name: string;
  route: { from: Labels; to: Labels };
  date: string;
  kilos: number | null;
  checkin_on: string | null;
  said_delivered: boolean;
  they_said_delivered: boolean;
  /** The flight has passed and this member has not said yes or no yet. */
  can_confirm: boolean;
  can_rate: boolean;
  my_stars: number | null;
}

export interface MyGoods {
  /** Null when the account is not a community member yet: membership is proved in the bot. */
  member: (GoodsMemberBadge & { active: boolean; blocked: boolean }) | null;
  bot_link: string | null;
  posts: MyGoodsPost[];
  deals: GoodsDeal[];
  limits: { max_open: number; open_traveller: number; open_sender: number };
}

export type GoodsInterestStatus = "sent" | "accepted" | "already" | "need_post" | "closed" | "own" | "blocked" | "limit" | "unreachable";

export interface GoodsInterestResult {
  status: GoodsInterestStatus;
  message: string;
  need?: { kind: GoodsKind; direction: GoodsDirection; date: string };
}

export interface GoodsCreated {
  post: MyGoodsPost;
  request: { status: string; message: string } | null;
  matches: GoodsCard[];
}

/** The open posts a post like the one being written would pair with, before it is posted. */
export interface GoodsFit {
  count: number;
  posts: GoodsCard[];
}
