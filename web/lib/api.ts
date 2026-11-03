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
}

export const LANGUAGE_NAMES: Record<string, string> = { am: "Amharic", ti: "Tigrinya", en: "English" };

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  return new Date(value).toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "short" });
}
