"use client";
import { Suspense, useEffect, useMemo, useState } from "react";
import { BadgeCheck, Store } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import toast from "react-hot-toast";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import { cn } from "@/lib/utils";
import ConfirmButton from "@/components/confirm-button";
import { EmptyState, ErrorState, LoadingRows, TableMessage } from "@/components/states";
import { toastResult } from "@/lib/toast";
import {
  useApi, useApiQuery, useApiMutation, formatDateTime, ApiError, errorText,
  type Business, type BusinessClaimRow, type BusinessesPage, type BusinessStatus,
} from "@/lib/api";
import { confirmDiscard, useLeaveGuard } from "@/lib/unsaved";
import { shrinkPhoto } from "@/lib/photos";

type Tab = BusinessStatus | "all" | "changes" | "claims";

const TABS: { value: Tab; label: string }[] = [
  { value: "pending", label: "To review" },
  { value: "changes", label: "Owner edits" },
  { value: "claims", label: "Claims" },
  { value: "approved", label: "Listed" },
  { value: "hidden", label: "Hidden" },
  { value: "rejected", label: "Rejected" },
  { value: "all", label: "All" },
];

const STATUS_COLOR = { pending: "warning", approved: "success", hidden: "destructive", rejected: "secondary" } as const;
const STATUS_LABEL: Record<BusinessStatus, string> = { pending: "to review", approved: "listed", hidden: "hidden", rejected: "rejected" };

const FIELD_LABEL: Record<string, string> = {
  name: "Name", category: "Kind", city_id: "City", area: "Location", maps_url: "Map link", latitude: "Latitude", longitude: "Longitude",
  phone: "Phone", whatsapp: "WhatsApp", telegram: "Telegram", website: "Website", description: "One line", about: "About",
  hours: "Opening hours", languages: "Languages",
};

type Form = {
  id?: number;
  name: string;
  city_id: string;
  category: string;
  area: string;
  maps_url: string;
  latitude: string;
  longitude: string;
  phone: string;
  whatsapp: string;
  telegram: string;
  website: string;
  description: string;
  about: string;
  hours: string;
  languages: string[];
  verified: boolean;
  // The photo picked in the dialog, sent after the listing is saved; or the live one taken down.
  photo: File | null;
  removePhoto: boolean;
};

const EMPTY: Form = {
  name: "", city_id: "", category: "restaurant", area: "", maps_url: "", latitude: "", longitude: "", phone: "", whatsapp: "",
  telegram: "", website: "", description: "", about: "", hours: "", languages: [], verified: false, photo: null, removePhoto: false,
};

const toForm = (b: Business): Form => ({
  id: b.id, name: b.name, city_id: String(b.city_id), category: b.category, area: b.area, maps_url: b.maps_url ?? "",
  latitude: b.latitude?.toString() ?? "", longitude: b.longitude?.toString() ?? "", phone: b.phone ?? "", whatsapp: b.whatsapp ?? "",
  telegram: b.telegram ?? "", website: b.website ?? "", description: b.description ?? "", about: b.about ?? "", hours: b.hours ?? "",
  languages: b.languages ?? [], verified: b.verified, photo: null, removePhoto: false,
});

const orNull = (s: string) => s.trim() || null;

const toBody = (f: Form) => ({
  name: f.name.trim(),
  city_id: Number(f.city_id),
  category: f.category,
  area: f.area.trim(),
  maps_url: orNull(f.maps_url),
  latitude: f.latitude.trim() ? Number(f.latitude) : null,
  longitude: f.longitude.trim() ? Number(f.longitude) : null,
  phone: orNull(f.phone),
  whatsapp: orNull(f.whatsapp),
  telegram: orNull(f.telegram),
  website: orNull(f.website),
  description: orNull(f.description),
  about: orNull(f.about),
  hours: orNull(f.hours),
  languages: f.languages,
  verified: f.verified,
});

/**
 * What to send on save. A new listing sends everything; an edit sends only the
 * fields the moderator changed, because the API drops an owner's queued change
 * to any field it is sent (a moderator's correction wins), and sending the whole
 * form would silently throw away every pending owner edit.
 */
function changedBody(form: Form, original: Business | null): Record<string, unknown> {
  const next = toBody(form) as Record<string, unknown>;
  if (!original) return next;
  const before = toBody(toForm(original)) as Record<string, unknown>;
  const changed = Object.fromEntries(Object.entries(next).filter(([k, v]) => JSON.stringify(v) !== JSON.stringify(before[k])));
  // A pin is two numbers: the API's required_with only fires when both keys are present.
  if ("latitude" in changed || "longitude" in changed) {
    changed.latitude = next.latitude;
    changed.longitude = next.longitude;
  }
  return changed;
}

/** A changed value, shown the way a person reads it. */
function show(field: string, value: unknown, data?: BusinessesPage): string {
  if (value === null || value === undefined || value === "") return "—";
  if (field === "category") return data?.choices.categories[String(value)] ?? String(value);
  if (field === "city_id") return data?.choices.cities.find((c) => c.id === Number(value))?.name_en ?? String(value);
  if (field === "languages" && Array.isArray(value)) return value.map((l) => data?.choices.languages?.[l] ?? l).join(", ") || "—";
  return String(value);
}

const ClaimsList = () => {
  const { data, isLoading, error } = useApiQuery<{ data: BusinessClaimRow[] }>(["business-claims"], "/admin/business-claims");
  const [notes, setNotes] = useState<Record<number, string>>({});
  const decide = useApiMutation<unknown, { id: number; action: "approve" | "reject" }>(
    (req, { id, action }) => req(`/admin/business-claims/${id}/${action}`, "POST", { note: notes[id] || null }),
    [["business-claims"], ["businesses"], ["dashboard"]]
  );

  const run = (claim: BusinessClaimRow, action: "approve" | "reject") =>
    toastResult(decide.mutateAsync({ id: claim.id, action }), action === "approve" ? "Handed over. The owner was told in Telegram." : "Claim rejected. The owner was told.");

  if (error) return <ErrorState error={error} title="Claims could not be loaded" />;
  if (isLoading) return <LoadingRows />;
  if (!data?.data.length) {
    return (
      <Card>
        <EmptyState icon={BadgeCheck} title="No ownership claims waiting">
          When an owner on the website says a listing the community added is theirs, the claim waits here for you to check.
        </EmptyState>
      </Card>
    );
  }

  return (
    <div className="space-y-3">
      {data.data.map((c) => (
        <Card key={c.id}>
          <CardContent className="p-4 space-y-3">
            <div className="flex flex-wrap justify-between gap-2">
              <div>
                <div className="font-medium text-default-900">{c.business?.name}</div>
                <div className="text-sm text-default-500">{c.business?.area}, {c.business?.city} · listed phone {c.business?.phone ?? "none"}</div>
              </div>
              <div className="text-sm text-right">
                <div>Claimed by <span className="font-medium">{c.owner?.name}</span>{c.owner?.username && <span className="text-default-500"> @{c.owner.username}</span>}</div>
                <div className="text-xs text-default-500">
                  {c.owner?.member_id ? `Verified member since ${formatDateTime(c.owner.member_since)}` : "Not a community member"} · {formatDateTime(c.created_at)}
                </div>
              </div>
            </div>
            {c.note && <blockquote className="border-l-4 border-default-300 pl-3 whitespace-pre-wrap text-default-800">{c.note}</blockquote>}
            <p className="text-xs text-default-500">Check before handing over: call the listed phone, or ask the claimant to message from it.</p>
            <Input placeholder="Note to the claimant if you reject" value={notes[c.id] ?? ""} onChange={(e) => setNotes({ ...notes, [c.id]: e.target.value })} />
            <div className="flex gap-2">
              <ConfirmButton
                size="sm"
                color="success"
                disabled={decide.isPending}
                label="Hand over"
                title={`Hand ${c.business?.name ?? "this listing"} to ${c.owner?.name ?? "this owner"}?`}
                description="They will be able to edit it from the website; their edits still come to you for review. Other claims on it are rejected."
                confirmLabel="Hand over"
                tone="default"
                onConfirm={() => run(c, "approve")}
              />
              <Button size="sm" variant="outline" color="destructive" disabled={decide.isPending} onClick={() => run(c, "reject").catch(() => undefined)}>Reject</Button>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
};

/** A form as text, to tell whether anything changed: a picked photo counts by its name and size. */
const snapshot = (form: Form | null): string => JSON.stringify(form, (_, value) => (value instanceof File ? `${value.name}:${value.size}` : value));

const DirectoryInner = () => {
  const router = useRouter();
  const params = useSearchParams();
  const status = (params.get("status") ?? "pending") as Tab;
  const cityId = params.get("city_id") ?? "";
  const category = params.get("category") ?? "";
  const search = params.get("search") ?? "";
  const page = Number(params.get("page") ?? "1");
  const [searchInput, setSearchInput] = useState(search);
  useEffect(() => setSearchInput(search), [search]);
  const [form, setForm] = useState<Form | null>(null);
  const [formBefore, setFormBefore] = useState("");
  const [current, setCurrent] = useState<Business | null>(null);
  // The picked photo as the browser shows it before upload; the object URL is released when it changes.
  const photoPreview = useMemo(() => (form?.photo ? URL.createObjectURL(form.photo) : null), [form?.photo]);
  useEffect(() => () => { if (photoPreview) URL.revokeObjectURL(photoPreview); }, [photoPreview]);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [note, setNote] = useState("");

  const query = new URLSearchParams({ status: status === "claims" ? "pending" : status, page: String(page) });
  if (cityId) query.set("city_id", cityId);
  if (category) query.set("category", category);
  if (search) query.set("search", search);

  const { data, isLoading, error, refetch } = useApiQuery<BusinessesPage>(["businesses", query.toString()], `/admin/businesses?${query.toString()}`, {
    placeholderData: (previous) => previous,
  });
  // The list row carries no reports; the open listing's own page does.
  const detail = useApiQuery<{ business: Business }>(["businesses", "one", current?.id], `/admin/businesses/${current?.id}`, { enabled: Boolean(current?.id) });
  const reports = detail.data?.business.reports ?? [];
  const ratings = detail.data?.business.ratings ?? [];

  const invalidate = [["businesses"], ["dashboard"], ["directory-cities"]];
  const removeRating = useApiMutation<unknown, number>((req, rating) => req(`/admin/businesses/${current?.id}/ratings/${rating}`, "DELETE"), [["businesses"]]);
  const { upload } = useApi();
  // The fields first (only the changed ones on an edit), then the photo against the saved listing's id.
  const save = useApiMutation<{ business: Business }, Form>(async (req, f) => {
    const changes = f.id ? changedBody(f, current) : toBody(f);
    let result: { business: Business } | null = null;
    if (!f.id || Object.keys(changes).length > 0) {
      result = f.id ? await req<{ business: Business }>(`/admin/businesses/${f.id}`, "PUT", changes) : await req<{ business: Business }>("/admin/businesses", "POST", changes);
      // The listing exists from here on: if the photo fails, a retry uploads again, it never creates a second listing.
      const saved: Business = result.business;
      setCurrent(saved);
      // What the server now holds (a Maps link may have filled the pin); only the photo may still be on its way.
      setFormBefore(snapshot(toForm(saved)));
      setForm((draft) => (draft ? { ...toForm(saved), photo: draft.photo, removePhoto: draft.removePhoto } : draft));
    }
    const id = result?.business.id ?? f.id;
    if (id && f.removePhoto && !f.photo) result = await req(`/admin/businesses/${id}/photo`, "DELETE");
    if (id && f.photo) {
      const body = new FormData();
      body.append("photo", await shrinkPhoto(f.photo));
      result = await upload(`/admin/businesses/${id}/photo`, body);
    }
    return result ?? { business: current as Business };
  }, invalidate);
  const act = useApiMutation<{ business?: Business }, { id: number; action: string; note?: string }>(
    (req, { id, action, note }) => (action === "delete" ? req(`/admin/businesses/${id}`, "DELETE") : req(`/admin/businesses/${id}/${action}`, "POST", { note })),
    invalidate
  );

  const update = (changes: Record<string, string>) => {
    const next = new URLSearchParams(params.toString());
    Object.entries(changes).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
    if (!("page" in changes)) next.delete("page");
    router.push(`/directory?${next.toString()}`);
  };

  const open = (b?: Business) => {
    setErrors({});
    setNote("");
    setCurrent(b ?? null);
    const next: Form = b ? toForm(b) : { ...EMPTY, city_id: String(data?.choices.cities[0]?.id ?? "") };
    setFormBefore(snapshot(next));
    setForm(next);
  };

  const submit = () => {
    if (!form) return;
    setErrors({});
    if (form.id && Object.keys(changedBody(form, current)).length === 0 && !form.photo && !form.removePhoto) {
      toast("Nothing changed.");
      return;
    }
    save
      .mutateAsync(form)
      .then(() => {
        toast.success(form.id ? "Saved." : "Added, listed and posted to the channel.");
        setForm(null);
      })
      .catch((e: ApiError) => {
        setErrors(e.errors ?? {});
        toast.error(errorText(e));
      });
  };

  const decide = (action: string, done: string): Promise<unknown> => {
    if (!current) return Promise.resolve();
    return toastResult(act.mutateAsync({ id: current.id, action, note: note || undefined }), done).then(() => setForm(null));
  };

  // What a stray click outside the dialog would throw away.
  const formDirty = form !== null && (snapshot(form) !== formBefore || note !== "");
  useLeaveGuard(formDirty);

  const err = (k: string) => errors[k] && <p className="text-xs text-destructive mt-1">{errors[k][0]}</p>;
  const counts = data?.counts ?? {};
  const total = (["pending", "approved", "hidden", "rejected"] as const).reduce((s, k) => s + (counts[k] ?? 0), 0);
  const changes = current?.pending_changes ? Object.entries(current.pending_changes) : [];

  return (
    <div>
      <PageTitle title="Business directory" description="Habesha businesses by city. Members suggest them in the bot and owners submit them on the website; nothing is listed until a moderator approves it, and approval posts it to the channel.">
        <Button onClick={() => open()}>Add business</Button>
      </PageTitle>

      <div className="flex flex-wrap items-center gap-2 mb-4">
        {TABS.map((tab) => (
          <button
            key={tab.value}
            type="button"
            onClick={() => update({ status: tab.value })}
            className={cn(
              "px-3 py-1.5 rounded-md text-sm font-medium border transition-colors",
              status === tab.value ? "bg-primary text-primary-foreground border-primary" : "bg-card text-default-700 border-default-200 hover:border-primary"
            )}
          >
            {tab.label}
            {data && <span className="ml-1.5 opacity-75">{tab.value === "all" ? total : counts[tab.value] ?? 0}</span>}
          </button>
        ))}
        {status !== "claims" && (
          <div className="flex flex-wrap gap-2 ml-auto">
            <Select value={cityId || "all"} onValueChange={(v) => update({ city_id: v === "all" ? "" : v })}>
              <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All cities</SelectItem>
                {data?.choices.cities.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name_en}</SelectItem>)}
              </SelectContent>
            </Select>
            <Select value={category || "all"} onValueChange={(v) => update({ category: v === "all" ? "" : v })}>
              <SelectTrigger className="w-52"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All kinds</SelectItem>
                {Object.entries(data?.choices.categories ?? {}).map(([k, label]) => <SelectItem key={k} value={k}>{label}</SelectItem>)}
              </SelectContent>
            </Select>
            <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); update({ search: searchInput.trim() }); }}>
              <Input value={searchInput} onChange={(e) => setSearchInput(e.target.value)} placeholder="Name, area or phone" className="w-48" />
              <Button type="submit" variant="outline">Search</Button>
            </form>
          </div>
        )}
      </div>

      {status === "claims" ? (
        <ClaimsList />
      ) : (
        <Card>
          <CardContent className="p-0">
            {error && <ErrorState className="m-4" error={error} onRetry={() => refetch()} title="Businesses could not be loaded" />}
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-16" />
                  <TableHead>Business</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Kind</TableHead>
                  <TableHead>City</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead>From</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading && <TableMessage colSpan={7}><LoadingRows className="p-4" /></TableMessage>}
                {data?.data.length === 0 && (
                  <TableMessage colSpan={7}>
                    <EmptyState icon={Store} compact title={search || cityId || category ? "No business matches" : status === "pending" ? "Nothing waiting for review" : status === "changes" ? "No owner edits waiting" : "Nothing here"}>
                      {search || cityId || category
                        ? "Try another city or kind, or search by name, area or phone."
                        : status === "pending"
                          ? "Suggestions from members and submissions from owners land here."
                          : status === "changes"
                            ? "When an owner edits a live listing, the change waits here while the live version stays up."
                            : "Add a business with the button above, or approve one waiting for review."}
                    </EmptyState>
                  </TableMessage>
                )}
                {data?.data.map((b) => (
                  <TableRow key={b.id} className="cursor-pointer" onClick={() => open(b)}>
                    <TableCell>
                      {b.pending_photo || b.photo ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={(b.pending_photo ?? b.photo) as string} alt="" className="h-10 w-14 rounded object-cover" />
                      ) : <div className="h-10 w-14 rounded bg-default-100" />}
                    </TableCell>
                    <TableCell className="min-w-[13rem] max-w-[18rem]">
                      {/* The row's one stop for the keyboard and for a screen reader: a real button with the listing's name. */}
                      <div className="font-medium text-default-900">
                        <button type="button" className="text-left hover:underline focus-visible:underline" onClick={(e) => { e.stopPropagation(); open(b); }}>{b.name}</button>
                        {b.verified && <span title="Verified"> ✅</span>}
                        {b.rating_count > 0 && <span className="ml-1.5 text-xs font-normal text-default-600 whitespace-nowrap">⭐ {b.rating_avg?.toFixed(1)} ({b.rating_count})</span>}
                      </div>
                      {b.description && <div className="text-xs text-default-500 truncate">{b.description}</div>}
                      {b.review_note && b.status !== "approved" && <div className="text-xs text-warning mt-1">{b.review_note}</div>}
                    </TableCell>
                    <TableCell className="whitespace-nowrap">
                      <Badge color={STATUS_COLOR[b.status]} variant="soft">{STATUS_LABEL[b.status]}</Badge>
                      {b.changes_submitted_at && <Badge color="info" variant="soft" className="ml-1">edits</Badge>}
                      {b.pending_photo && <Badge color="info" variant="outline" className="ml-1">photo</Badge>}
                      {b.claims_pending > 0 && <Badge color="warning" variant="outline" className="ml-1">claimed</Badge>}
                      {b.reports_count > 0 && <Badge color="destructive" variant="outline" className="ml-1">{b.reports_count} report{b.reports_count === 1 ? "" : "s"}</Badge>}
                    </TableCell>
                    <TableCell className="text-sm whitespace-nowrap">{data.choices.categories[b.category] ?? b.category}</TableCell>
                    <TableCell className="text-sm whitespace-nowrap">{b.city}</TableCell>
                    <TableCell className="text-sm font-mono whitespace-nowrap">{b.phone ?? "—"}</TableCell>
                    <TableCell className="text-sm whitespace-nowrap">
                      {b.owner ? <span>🏪 {b.owner.name}</span> : b.submitted_by?.name ?? <span className="text-default-500">admin</span>}
                      <div className="text-xs text-default-500">{formatDateTime(b.changes_submitted_at ?? b.submitted_at ?? b.created_at)}</div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {data && data.meta.last_page > 1 && (
              <div className="flex items-center justify-between p-4 border-t border-default-200">
                <span className="text-sm text-default-500">Page {data.meta.page} of {data.meta.last_page}</span>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => update({ page: String(page - 1) })}>Previous</Button>
                  <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => update({ page: String(page + 1) })}>Next</Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      <Dialog open={form !== null} onOpenChange={(o) => { if (!o && confirmDiscard(formDirty)) setForm(null); }}>
        <DialogContent size="3xl" className="max-h-[90vh] overflow-y-auto">
          {form && (
            <>
              <DialogHeader>
                <DialogTitle>{form.id ? form.name || "Business" : "Add a business"}</DialogTitle>
                <DialogDescription>
                  {current?.owner
                    ? `Managed by its owner ${current.owner.name}${current.owner.username ? ` (@${current.owner.username})` : ""}, submitted ${formatDateTime(current.submitted_at ?? current.created_at)}.`
                    : current?.submitted_by
                      ? `Suggested by ${current.submitted_by.name} on ${formatDateTime(current.created_at)}.`
                      : form.id ? "Added by an admin." : "Added here, it is listed at once and posted to the channel."}
                  {current && current.views > 0 && ` ${current.views} views on the website.`}
                </DialogDescription>
              </DialogHeader>

              {current && (current.photo || current.pending_photo) && (
                <div className="flex flex-wrap gap-4">
                  {current.photo && (
                    <figure>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={current.photo} alt="" className="h-36 rounded-md object-cover" />
                      <figcaption className="text-xs text-default-500 mt-1">Live photo</figcaption>
                    </figure>
                  )}
                  {current.pending_photo && (
                    <figure>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={current.pending_photo} alt="" className="h-36 rounded-md object-cover ring-2 ring-info" />
                      <figcaption className="text-xs text-info mt-1">New photo, waiting for review</figcaption>
                    </figure>
                  )}
                </div>
              )}

              {current && current.changes_submitted_at && (
                <div className="rounded-md border border-info/40 bg-info/5 p-3 space-y-3">
                  <div className="text-sm font-medium text-default-900">The owner changed this listing on {formatDateTime(current.changes_submitted_at)}. The live listing stays as it is until you decide.</div>
                  {changes.length > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow><TableHead>Field</TableHead><TableHead>Live now</TableHead><TableHead>Owner wants</TableHead></TableRow>
                      </TableHeader>
                      <TableBody>
                        {changes.map(([field, value]) => (
                          <TableRow key={field}>
                            <TableCell className="text-sm font-medium">{FIELD_LABEL[field] ?? field}</TableCell>
                            <TableCell className="text-sm text-default-500 whitespace-pre-wrap">{show(field, (current as unknown as Record<string, unknown>)[field], data)}</TableCell>
                            <TableCell className="text-sm whitespace-pre-wrap">{show(field, value, data)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : <div className="text-sm text-default-600">Only the photo changed.</div>}
                  <Input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Reason, sent to the owner if you reject" />
                  <div className="flex gap-2">
                    <Button size="sm" color="success" disabled={act.isPending} onClick={() => decide("changes/approve", "Changes are live; the channel post was updated and the owner told.").catch(() => undefined)}>Approve changes</Button>
                    <Button size="sm" variant="outline" color="destructive" disabled={act.isPending} onClick={() => decide("changes/reject", "Changes rejected; the owner was told.").catch(() => undefined)}>Reject changes</Button>
                  </div>
                </div>
              )}

              <div className="grid gap-4 md:grid-cols-2">
                <div className="md:col-span-2">
                  <Label htmlFor="directory-business-name" className="mb-1.5 block">Business name *</Label>
                  <Input id="directory-business-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                  {err("name")}
                </div>
                <div>
                  <Label htmlFor="directory-city" className="mb-1.5 block">City *</Label>
                  <Select value={form.city_id} onValueChange={(v) => setForm({ ...form, city_id: v })}>
                    <SelectTrigger id="directory-city"><SelectValue placeholder="Pick a city" /></SelectTrigger>
                    <SelectContent>{data?.choices.cities.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name_en}{!c.active && " (off)"}</SelectItem>)}</SelectContent>
                  </Select>
                  {err("city_id")}
                </div>
                <div>
                  <Label htmlFor="directory-kind" className="mb-1.5 block">Kind *</Label>
                  <Select value={form.category} onValueChange={(v) => setForm({ ...form, category: v })}>
                    <SelectTrigger id="directory-kind"><SelectValue /></SelectTrigger>
                    <SelectContent>{Object.entries(data?.choices.categories ?? {}).map(([k, label]) => <SelectItem key={k} value={k}>{label}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div className="md:col-span-2">
                  <Label htmlFor="directory-location-area-or-street" className="mb-1.5 block">Location: area or street *</Label>
                  <Input id="directory-location-area-or-street" value={form.area} onChange={(e) => setForm({ ...form, area: e.target.value })} placeholder="Deira, near Al Rigga metro" />
                  {err("area")}
                </div>
                <div className="md:col-span-2">
                  <Label htmlFor="directory-google-maps-link" className="mb-1.5 block">Google Maps link</Label>
                  <Input id="directory-google-maps-link" value={form.maps_url} onChange={(e) => setForm({ ...form, maps_url: e.target.value })} placeholder="https://maps.app.goo.gl/…" />
                  <p className="text-xs text-default-500 mt-1">A link with coordinates in it fills the pin below on save.</p>
                  {err("maps_url")}
                </div>
                <div>
                  <Label htmlFor="directory-latitude" className="mb-1.5 block">Latitude</Label>
                  <Input id="directory-latitude" value={form.latitude} onChange={(e) => setForm({ ...form, latitude: e.target.value })} placeholder="25.2697" />
                  {err("latitude")}
                </div>
                <div>
                  <Label htmlFor="directory-longitude" className="mb-1.5 block">Longitude</Label>
                  <Input id="directory-longitude" value={form.longitude} onChange={(e) => setForm({ ...form, longitude: e.target.value })} placeholder="55.3095" />
                  {err("longitude")}
                </div>
                <div>
                  <Label htmlFor="directory-phone" className="mb-1.5 block">Phone</Label>
                  <Input id="directory-phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="04 123 4567" />
                  {err("phone")}
                </div>
                <div>
                  <Label htmlFor="directory-whatsapp" className="mb-1.5 block">WhatsApp</Label>
                  <Input id="directory-whatsapp" value={form.whatsapp} onChange={(e) => setForm({ ...form, whatsapp: e.target.value })} placeholder="050 123 4567" />
                  {err("whatsapp")}
                </div>
                <div>
                  <Label htmlFor="directory-telegram" className="mb-1.5 block">Telegram</Label>
                  <Input id="directory-telegram" value={form.telegram} onChange={(e) => setForm({ ...form, telegram: e.target.value })} placeholder="@username" />
                  {err("telegram")}
                </div>
                <div>
                  <Label htmlFor="directory-website" className="mb-1.5 block">Website</Label>
                  <Input id="directory-website" value={form.website} onChange={(e) => setForm({ ...form, website: e.target.value })} placeholder="https://…" />
                  {err("website")}
                </div>
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">Photo</Label>
                  <div className="flex flex-wrap items-center gap-3">
                    {(photoPreview || (current?.photo && !form.removePhoto)) && (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={photoPreview ?? (current?.photo as string)} alt="" className={cn("h-20 w-28 rounded-md object-cover", photoPreview && "ring-2 ring-primary")} />
                    )}
                    <Input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      className="max-w-xs"
                      aria-label="Photo of the place"
                      onChange={(e) => setForm({ ...form, photo: e.target.files?.[0] ?? null, removePhoto: false })}
                    />
                    {current?.photo && !form.photo && (
                      <Button type="button" variant="outline" color={form.removePhoto ? "secondary" : "destructive"} size="sm" onClick={() => setForm({ ...form, removePhoto: !form.removePhoto })}>
                        {form.removePhoto ? "Keep the photo" : "Remove the photo"}
                      </Button>
                    )}
                  </div>
                  <p className="text-xs text-default-500 mt-1">
                    JPEG, PNG or WebP, up to 8 MB. A photo you add here is live at once{form.id ? "" : ", with the listing"}; it replaces the monogram on the website, the bot and the channel post.
                  </p>
                  {err("photo")}
                </div>
                <div className="md:col-span-2">
                  <Label htmlFor="directory-one-line-about-it" className="mb-1.5 block">One line about it</Label>
                  <Textarea id="directory-one-line-about-it" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                  {err("description")}
                </div>
                <div className="md:col-span-2">
                  <Label htmlFor="directory-about-shown-on-its-page" className="mb-1.5 block">About (shown on its page)</Label>
                  <Textarea id="directory-about-shown-on-its-page" rows={4} value={form.about} onChange={(e) => setForm({ ...form, about: e.target.value })} />
                  {err("about")}
                </div>
                <div>
                  <Label htmlFor="directory-opening-hours" className="mb-1.5 block">Opening hours</Label>
                  <Input id="directory-opening-hours" value={form.hours} onChange={(e) => setForm({ ...form, hours: e.target.value })} placeholder="Every day 10:00–23:00" />
                  {err("hours")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Languages spoken</Label>
                  <div className="flex flex-wrap gap-1.5">
                    {Object.entries(data?.choices.languages ?? {}).map(([code, label]) => {
                      const on = form.languages.includes(code);
                      return (
                        <button key={code} type="button"
                          onClick={() => setForm({ ...form, languages: on ? form.languages.filter((l) => l !== code) : [...form.languages, code] })}
                          className={cn("px-2.5 py-1 rounded-full text-xs border", on ? "bg-primary text-primary-foreground border-primary" : "border-default-300 text-default-700")}>
                          {label}
                        </button>
                      );
                    })}
                  </div>
                </div>
                <label className="flex items-center gap-2 text-sm md:col-span-2">
                  <Switch checked={form.verified} onCheckedChange={(v) => setForm({ ...form, verified: v })} />
                  Verified: someone on the team has confirmed this business is real and the details are right
                </label>
              </div>

              {reports.length > 0 && (
                <div className="rounded-lg border border-destructive/20 bg-destructive/[0.03] p-3 text-sm">
                  <div className="font-medium text-default-900 mb-1">Reports of wrong information ({reports.length})</div>
                  <ul className="space-y-1">{reports.map((r, i) => <li key={i}><span className="text-default-800">{r.reason || "No reason given"}</span> <span className="text-default-500">· {r.member ?? "A member"} · {formatDateTime(r.at)}</span></li>)}</ul>
                </div>
              )}

              {ratings.length > 0 && (
                <div className="rounded-lg border border-default-200 p-3 text-sm">
                  <div className="font-medium text-default-900 mb-1">
                    Member ratings: ⭐ {detail.data?.business.rating_avg?.toFixed(1)} from {detail.data?.business.rating_count}
                  </div>
                  <p className="text-xs text-default-500 mb-2">The directory is ordered by these. A banned member&apos;s stars do not count. Take off any that look fake.</p>
                  <ul className="space-y-1.5 max-h-56 overflow-y-auto">
                    {ratings.map((r) => (
                      <li key={r.id} className="flex items-center justify-between gap-2">
                        <span className="min-w-0">
                          <span className="font-medium text-default-800">{r.stars} ⭐</span>{" "}
                          <span className={r.member?.banned ? "text-destructive" : "text-default-700"}>{r.member?.name ?? "A former member"}{r.member?.banned ? " (banned)" : ""}</span>
                          {r.member?.username && <span className="text-default-500"> @{r.member.username}</span>}
                          <span className="text-default-500"> · {formatDateTime(r.at)}</span>
                        </span>
                        <ConfirmButton size="xs" variant="ghost" color="destructive" disabled={removeRating.isPending} label="Remove"
                          title="Remove this rating"
                          description="The stars come off this listing and its place in the directory is worked out again. The member can rate it again."
                          confirmLabel="Remove" tone="destructive"
                          onConfirm={() => removeRating.mutateAsync(r.id).then(() => { toast.success("Rating removed."); }).catch((e: Error) => { toast.error(errorText(e)); throw e; })} />
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {current && current.status !== "approved" && !current.changes_submitted_at && (
                <div>
                  <Label htmlFor="directory-reason-sent-to-the-owner-with-a-rejection" className="mb-1.5 block">Reason (sent to the owner with a rejection)</Label>
                  <Input id="directory-reason-sent-to-the-owner-with-a-rejection" value={note} onChange={(e) => setNote(e.target.value)} placeholder="Closed down, or could not confirm" />
                </div>
              )}

              <DialogFooter className="flex-wrap gap-2">
                {current && current.status !== "approved" && (
                  <Button color="success" disabled={act.isPending} onClick={() => decide("approve", current.status === "hidden" ? "Listed again; reports cleared." : "Approved: live on the website and posted to the channel.").catch(() => undefined)}>
                    {current.status === "hidden" ? "List again" : "Approve"}
                  </Button>
                )}
                {current && current.status === "pending" && (
                  <ConfirmButton color="destructive" variant="outline" disabled={act.isPending} label="Reject"
                    title={`Reject ${current.name}?`}
                    description={note ? `The person who sent it is told why: “${note}”.` : "The person who sent it is told it was not listed. Write a reason in the box above if they should know why."}
                    confirmLabel="Reject" onConfirm={() => decide("reject", "Rejected.")} />
                )}
                {current && current.status === "approved" && (
                  <ConfirmButton color="warning" variant="outline" disabled={act.isPending} label="Hide"
                    title={`Hide ${current.name}?`}
                    description="It leaves the website and the bot, and its channel post is removed. You can list it again from the Hidden tab."
                    confirmLabel="Hide" tone="warning" onConfirm={() => decide("hide", "Hidden from the directory and removed from the channel.")} />
                )}
                {current && (
                  <ConfirmButton color="destructive" variant="ghost" disabled={act.isPending} label="Delete"
                    title={`Delete ${current.name} for good?`}
                    description="The listing, its photos and its reports are removed and cannot be brought back. To take it down for now, hide it instead."
                    confirmLabel="Delete for good" onConfirm={() => decide("delete", "Deleted.")} />
                )}
                <Button disabled={save.isPending} onClick={submit}>{form.id ? "Save changes" : "Add and list"}</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};

const DirectoryPage = () => (
  <Suspense>
    <DirectoryInner />
  </Suspense>
);

export default DirectoryPage;
