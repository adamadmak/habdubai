"use client";
import { useCallback } from "react";
import { useSession, signOut } from "next-auth/react";
import { useQuery, useMutation, useQueryClient, type UseQueryOptions } from "@tanstack/react-query";

export const API_URL = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/$/, "");

export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

/** Several queries can meet the same 401 at once; sign out only once. */
let signingOut = false;

/**
 * What a person reads when a request fails: the API's own sentence when it
 * wrote one for people (validation, "only a pending announcement…"), and a
 * plain explanation otherwise. Never a stack trace, HTML or raw JSON.
 */
function readableError(status: number, data: unknown): string {
  const body = (data && typeof data === "object" ? data : {}) as { message?: unknown; errors?: Record<string, string[]> };
  const message = typeof body.message === "string" ? body.message.trim() : "";
  const looksMachine = message === "" || /^[[{<]/.test(message) || message.length > 300 || /^(Server Error|Unauthenticated.?|Forbidden|Not Found|Too Many Attempts.?)$/i.test(message)
    // Laravel's own wording for a missing model or route names classes and paths.
    || /No query results for model|could not be found|SQLSTATE|Exception/i.test(message);

  if (status >= 500) return "The server hit an error. Try again; if it keeps happening, check the API logs.";
  if (status === 401) return "Your session has ended. Sign in again.";
  if (status === 429) return "Too many requests at once. Wait a minute and try again.";
  if (status === 419) return "The page was open too long. Reload it and try again.";
  if (!looksMachine) return message;
  if (status === 422) return Object.values(body.errors ?? {}).flat()[0] ?? "Some of the values are not accepted.";
  if (status === 403) return "This account may not do that.";
  if (status === 404) return "Not found. It may have been deleted, or the API does not offer this yet.";
  if (status === 409) return "Someone else changed this at the same time. Reload and try again.";
  return `The request failed (${status}).`;
}

export async function apiRequest<T>(path: string, token: string | undefined, method: Method = "GET", body?: unknown): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${API_URL}/api${path}`, {
      method,
      headers: {
        Accept: "application/json",
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      cache: "no-store",
    });
  } catch {
    throw new ApiError(0, "Could not reach the API. Check the connection and try again.");
  }

  const text = await response.text();
  let data: any = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    // A proxy's HTML error page, or a truncated answer: no JSON to read.
    data = null;
  }

  if (!response.ok) {
    if (response.status === 401 && !signingOut) {
      signingOut = true;
      // Through next-auth, whose signOut event also revokes the token on the API.
      // Back to this page after signing in again; the path without the locale prefix, as the login form expects.
      const path = window.location.pathname.replace(/^\/(en|am|ti|om)(?=\/|$)/, "") + window.location.search;
      const next = path && path !== "/" && !/^\/auth\//.test(path) ? `&next=${encodeURIComponent(path)}` : "";
      await signOut({ callbackUrl: `/auth/login?expired=1${next}` });
    }
    if (response.status >= 500) console.error(`API ${method} ${path} failed (${response.status})`, data ?? text.slice(0, 500));
    throw new ApiError(response.status, readableError(response.status, data), data?.errors ?? {});
  }

  if (data === null && text) {
    throw new ApiError(response.status, "The API answered with something that is not JSON. Check the API address on this app.");
  }

  return data as T;
}

/** The sentence to put in a toast for any thrown value. */
export function errorText(error: unknown): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return "Something went wrong. Try again.";
}

/** A request function bound to the signed-in admin's token. */
export function useApi() {
  const { data: session } = useSession();
  const token = session?.accessToken;

  const request = useCallback(
    <T,>(path: string, method: Method = "GET", body?: unknown) => apiRequest<T>(path, token, method, body),
    [token]
  );

  return { request, ready: Boolean(token) };
}

export function useApiQuery<T>(key: unknown[], path: string, options?: Omit<UseQueryOptions<T>, "queryKey" | "queryFn">) {
  const { request, ready } = useApi();
  return useQuery<T>({
    queryKey: key,
    queryFn: () => request<T>(path),
    ...options,
    // After the options, so a caller's `enabled` cannot run a query before the session has a token.
    enabled: ready && (options?.enabled ?? true),
  });
}

export function useApiMutation<TResult, TVars = void>(
  fn: (request: <T>(path: string, method?: Method, body?: unknown) => Promise<T>, vars: TVars) => Promise<TResult>,
  invalidate: unknown[][] = []
) {
  const { request } = useApi();
  const queryClient = useQueryClient();

  return useMutation<TResult, ApiError, TVars>({
    mutationFn: (vars) => fn(request, vars),
    onSuccess: () => {
      invalidate.forEach((key) => queryClient.invalidateQueries({ queryKey: key }));
    },
  });
}

// ---- shapes the API answers with -------------------------------------------

export type MemberStatus = "active" | "onboarding" | "banned" | "left";

export interface Member {
  id: number;
  telegram_user_id: string;
  telegram_username: string | null;
  display_name: string;
  phone_last4: string;
  phone_country: string;
  needs_review: boolean;
  emirate: string | null;
  trades: string[];
  language: string;
  role: "member" | "leader" | "moderator" | "admin";
  status: MemberStatus;
  verified_at: string | null;
  agreed_rules_at: string | null;
  left_at: string | null;
  banned_at: string | null;
  ban_reason: string | null;
  helper_points: number;
  trips_completed: number;
  rating_avg: string | null;
  rating_count: number;
}

export type Labels = Record<string, Record<string, string>>;

export interface MembersPage {
  data: Member[];
  meta: { total: number; page: number; last_page: number; per_page: number };
  choices: { emirates: Labels; trades: Labels; roles: string[] };
}

export interface AuditEntry {
  id: number;
  actor_id: number | null;
  actor?: { id: number; name: string; email: string } | null;
  action: string;
  subject_type: string | null;
  subject_id: number | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  created_at: string;
}

export interface BotTextRow {
  key: string;
  is_button: boolean;
  default: Record<string, string>;
  override: Record<string, string>;
  placeholders: string[];
}

export interface SettingsPayload {
  settings: Record<string, string>;
  /** Write-only settings: whether each one is stored. */
  secrets?: Record<string, boolean>;
  chats?: BotChatRow[];
  telegram: {
    configured: boolean;
    username: string;
    webhook: null | { url?: string; pending_update_count?: number; last_error_message?: string | null; last_error_date?: string | null; error?: string };
  };
}

export interface DashboardPayload {
  members: { total: number; active: number; onboarding: number; verified_today: number; needs_review: number; banned: number; left: number };
  by_emirate: Record<string, number>;
  by_language: Record<string, number>;
  updates: { today: number; failed: number };
  bot: { configured: boolean; username: string };
  jobs: { published_today: number; open: number; review: number; duplicates_today: number; alerts_queued: number; classifier: "claude" | "rules"; channel: boolean };
  sources: { active: number; paused: number; failing: number };
  directory: { listed: number; pending: number; hidden: number; owner_edits: number; claims: number; owners: number };
  community: { questions_open: number; questions_today: number; announcements_pending: number; safety_open: number; faq_live: number; reports_open: number };
  goods: { open_posts: number; live_matches: number; disputes: number; completed: number };
  tick: { at: string; seconds: number } | null;
}

/** Website page views (`GET /admin/analytics?days=N`): counts per day and path, no personal data. */
export interface SiteAnalytics {
  total: number;
  /** Oldest first, one row per day including days with no views. */
  days: { day: string; views: number }[];
  /** Up to ten paths, most viewed first. */
  top: { path: string; views: number }[];
}

export interface GoodsMember {
  id: number;
  name: string;
  username: string | null;
  verified_at: string | null;
  deals: number;
  rating_avg: number | null;
  rating_count: number;
  failures_year: number;
}

export interface GoodsPostRow {
  id: number;
  kind: "traveller" | "sender";
  direction: "to_et" | "to_ae";
  route: string;
  date: string;
  kilos: number;
  kilos_left: number | null;
  categories: string[];
  description: string | null;
  status: "open" | "matched" | "expired" | "closed" | "hidden";
  open: boolean;
  hidden_reason: string | null;
  in_group: boolean;
  member: GoodsMember | null;
  created_at: string;
  /** A carry fee members pay each other directly (since 3 Nov 2026); null means "to agree". Absent on older APIs. */
  price?: number | null;
  currency?: "AED" | "ETB" | null;
  mode?: "send" | "request" | null;
}

export type GoodsMatchStatus =
  | "requested" | "introduced" | "agreed" | "disputed" | "completed" | "failed" | "cancelled" | "declined" | "expired" | "unconfirmed";

export interface GoodsMatchRow {
  id: number;
  status: GoodsMatchStatus;
  route: string | null;
  flight_date: string | null;
  kilos: number | null;
  parcel: string | null;
  traveller: GoodsMember | null;
  sender: GoodsMember | null;
  owner_id: number;
  requester_id: number;
  checkin_on: string | null;
  checkin: { traveller: string | null; sender: string | null };
  ratings: { from_member_id: number; stars: number; visible: boolean }[];
  failed_member_id: number | null;
  moderator_note: string | null;
  introduced_at: string | null;
  agreed_at: string | null;
  closed_at: string | null;
  created_at: string;
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  return new Date(value + (value.length === 10 ? "T00:00:00" : "")).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
}

export type JobStatus = "pending" | "review" | "published" | "rejected" | "duplicate" | "closed";

export interface JobSummary {
  id: number;
  title: string;
  company: string | null;
  category: string;
  emirate: string;
  salary: string | null;
  status: JobStatus;
  review_reason: string | null;
  scam_score: number;
  reports_count: number;
  url: string | null;
  source: string | null;
  source_id: number | null;
  on_channel: boolean;
  created_at: string;
  published_at: string | null;
}

export interface JobDetail extends JobSummary {
  summary_am: string | null;
  summary_ti: string | null;
  summary_en: string | null;
  summary_om: string | null;
  languages: string[];
  accommodation: "yes" | "no" | "unknown";
  visa: "yes" | "no" | "unknown";
  sub_area: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  scam_reasons: string[];
  classified_by: string;
  duplicate_of: number | null;
  raw_text: string | null;
  reports: { member: string | null; reason: string; at: string }[];
}

export interface JobsPage {
  data: JobSummary[];
  meta: { total: number; page: number; last_page: number };
  counts: Partial<Record<JobStatus, number>>;
}

export type SourceKind = "rss" | "html" | "telegram";

export interface JobSourceRow {
  id: number;
  key: string;
  name: string;
  kind: SourceKind;
  config: Record<string, string | number | null>;
  interval_minutes: number;
  active: boolean;
  built_in: boolean;
  status: "ok" | "failing" | "paused" | "off";
  paused_at: string | null;
  pause_reason: string | null;
  last_run_at: string | null;
  last_success_at: string | null;
  last_error: string | null;
  failures_in_row: number;
  last_found: number;
  last_new: number;
  last_7_days?: Partial<Record<JobStatus, number>>;
}

export interface SourceTestResult {
  ok: boolean;
  error?: string;
  found?: number;
  listings: { external_key: string; url: string | null; title: string | null; company: string | null; location: string | null; text: string; posted_at: string | null }[];
}

export interface JobSettingsPayload {
  settings: {
    job_channel_id: string;
    job_review_threshold: number;
    job_warning_threshold: number;
    job_auto_publish: boolean;
    job_card_languages: string;
    job_scam_phrases: string;
    job_blocked_companies: string;
    anthropic_model: string;
  };
  anthropic: { configured: boolean; hint: string | null };
}

export type BusinessStatus = "pending" | "approved" | "rejected" | "hidden";

export interface Business {
  id: number;
  name: string;
  category: string;
  city_id: number;
  city: string | null;
  area: string;
  latitude: number | null;
  longitude: number | null;
  maps_url: string | null;
  map_link: string;
  phone: string | null;
  whatsapp: string | null;
  telegram: string | null;
  website: string | null;
  description: string | null;
  about: string | null;
  hours: string | null;
  languages: string[];
  slug: string | null;
  status: BusinessStatus;
  verified: boolean;
  review_note: string | null;
  reports_count: number;
  views: number;
  photo: string | null;
  pending_photo: string | null;
  pending_changes: Record<string, unknown> | null;
  changes_submitted_at: string | null;
  claims_pending: number;
  posted_to_channel: boolean;
  submitted_by: { id: number; name: string } | null;
  owner: { id: number; name: string; username: string | null } | null;
  submitted_at: string | null;
  created_at: string;
  reports?: { member: string | null; reason: string; at: string }[];
  claims?: BusinessClaimRow[];
}

export interface BusinessClaimRow {
  id: number;
  status: "pending" | "approved" | "rejected";
  note: string | null;
  review_note: string | null;
  business: { id: number; name: string; area: string; city: string | null; phone: string | null; owner_id: number | null } | null;
  owner: { id: number; name: string; username: string | null; member_id: number | null; member_since: string | null } | null;
  created_at: string;
}

export interface DirectoryCity {
  id: number;
  key: string;
  name_en: string;
  name_am: string;
  name_ti: string;
  name_om: string | null;
  country: string;
  sort: number;
  active: boolean;
  listed_count?: number;
  pending_count?: number;
}

export interface BusinessesPage {
  data: Business[];
  meta: { total: number; page: number; last_page: number };
  counts: Partial<Record<BusinessStatus | "changes" | "claims", number>>;
  choices: { categories: Record<string, string>; cities: { id: number; name_en: string; active: boolean }[]; languages: Record<string, string> };
}

export interface QuestionRow {
  id: number;
  title: string;
  category: string;
  status: "open" | "solved" | "closed";
  anonymous: boolean;
  asker: { id: number; name: string } | null;
  replies_count: number;
  faq_id: number | null;
  on_forum: boolean;
  created_at: string;
  last_activity_at: string | null;
}

export interface QuestionDetail extends QuestionRow {
  body: string;
  replies: { id: number; member: { id: number; name: string; points: number } | null; text: string; points_awarded: number; is_solution: boolean; created_at: string }[];
}

export interface FaqRow {
  id: number;
  question_id: number | null;
  category: string;
  q_am: string | null;
  q_ti: string | null;
  q_en: string;
  a_am: string | null;
  a_ti: string | null;
  a_en: string;
  q_om: string | null;
  a_om: string | null;
  source_url: string | null;
  active: boolean;
}

export interface KnowledgeRow {
  id: number;
  title: string;
  body: string;
  source_name: string | null;
  source_url: string | null;
  active: boolean;
}

export interface EmployerReportRow {
  id: number;
  employer_name: string;
  times_named: number;
  text: string;
  contactable: boolean;
  member: { id: number; name: string; username: string | null } | null;
  status: "open" | "closed";
  note: string | null;
  created_at: string;
}

export interface AnnouncementTopicRow {
  id: number;
  key: string;
  name_en: string;
  name_am: string;
  name_ti: string;
  name_om: string | null;
  emoji: string;
  needs_approval: boolean;
  default_on: boolean;
  pin: boolean;
  sort: number;
  active: boolean;
}

export interface AnnouncementRow {
  id: number;
  topic: { id: number; name: string; emoji: string; needs_approval: boolean } | null;
  poster: { id: number | null; name: string; role: string } | null;
  original_language: string;
  text_am: string;
  text_ti: string;
  text_en: string;
  text_om: string | null;
  has_photo: boolean;
  event_date: string | null;
  event_place: string | null;
  status: "pending" | "published" | "rejected" | "deleted";
  review_note: string | null;
  on_channel: boolean;
  pinned_until: string | null;
  dm_count: number;
  published_at: string | null;
  created_at: string;
}

export interface BotChatRow {
  chat_id: number;
  type: string;
  title: string | null;
  username: string | null;
  is_forum: boolean;
  bot_status: string;
}

export const CATEGORY_NAMES: Record<string, string> = {
  domestic: "Domestic work", driver: "Driver", security: "Security", cleaning: "Cleaning", hospitality: "Hospitality",
  salon: "Salon and beauty", retail: "Retail and sales", warehouse: "Warehouse and delivery", nursing: "Nursing and care",
  office: "Office and admin", construction: "Construction and technical", other: "Other",
};

export const JOB_EMIRATE_NAMES: Record<string, string> = {
  dubai: "Dubai", sharjah: "Sharjah", abu_dhabi: "Abu Dhabi", ajman: "Ajman", other_uae: "Other emirate", outside_uae: "Outside the UAE", unknown: "Not stated",
};

export const LANGUAGE_NAMES: Record<string, string> = { am: "Amharic", ti: "Tigrinya", om: "Afaan Oromoo", en: "English" };

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  return new Date(value).toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "short" });
}

// ---- launch readiness --------------------------------------------------------

export type ReadinessSeverity = "blocker" | "important" | "nice";

export interface ReadinessItem {
  key: string;
  label: string;
  ok: boolean;
  severity: ReadinessSeverity;
  detail: string;
  fix: string;
  /** An admin page (e.g. "/settings") or a full URL; null when the fix is outside the admin. */
  href: string | null;
}

/** `GET /admin/readiness`: what still stands between today and launch. */
export interface ReadinessPayload {
  score: number;
  ready: boolean;
  items: ReadinessItem[];
}

/** `GET /health`: is the running API well. */
export interface HealthPayload {
  ok: boolean;
  db: boolean | string;
  tick_age_seconds: number | null;
  queue_pending: number;
  failed_updates_24h: number;
}

/** "just now", "4 min ago", "3 h ago", "2 days ago". */
export function timeAgo(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined || !Number.isFinite(seconds)) return "never";
  const s = Math.max(0, Math.round(seconds));
  if (s < 45) return "just now";
  if (s < 3600) return `${Math.round(s / 60)} min ago`;
  if (s < 86400) return `${Math.round(s / 3600)} h ago`;
  const days = Math.round(s / 86400);
  return `${days} day${days === 1 ? "" : "s"} ago`;
}
