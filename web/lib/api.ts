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

export async function apiRequest<T>(path: string, token: string | undefined, method: Method = "GET", body?: unknown): Promise<T> {
  const response = await fetch(`${API_URL}/api${path}`, {
    method,
    headers: {
      Accept: "application/json",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
    cache: "no-store",
  });

  const text = await response.text();
  const data = text ? (JSON.parse(text) as any) : null;

  if (!response.ok) {
    if (response.status === 401) {
      await signOut({ callbackUrl: "/auth/login" });
    }
    throw new ApiError(response.status, data?.message ?? `Request failed (${response.status})`, data?.errors ?? {});
  }

  return data as T;
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
    enabled: ready && (options?.enabled ?? true),
    ...options,
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
  directory: { listed: number; pending: number; hidden: number };
  tick: { at: string; seconds: number } | null;
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
  telegram: string | null;
  description: string | null;
  status: BusinessStatus;
  verified: boolean;
  review_note: string | null;
  reports_count: number;
  submitted_by: { id: number; name: string } | null;
  created_at: string;
  reports?: { member: string | null; reason: string; at: string }[];
}

export interface DirectoryCity {
  id: number;
  key: string;
  name_en: string;
  name_am: string;
  name_ti: string;
  country: string;
  sort: number;
  active: boolean;
  listed_count?: number;
  pending_count?: number;
}

export interface BusinessesPage {
  data: Business[];
  meta: { total: number; page: number; last_page: number };
  counts: Partial<Record<BusinessStatus, number>>;
  choices: { categories: Record<string, string>; cities: { id: number; name_en: string; active: boolean }[] };
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

export const LANGUAGE_NAMES: Record<string, string> = { am: "Amharic", ti: "Tigrinya", en: "English" };

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  return new Date(value).toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "short" });
}
