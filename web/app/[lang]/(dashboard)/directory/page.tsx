"use client";
import { Suspense, useState } from "react";
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
import {
  useApiQuery, useApiMutation, formatDateTime, ApiError,
  type Business, type BusinessClaimRow, type BusinessesPage, type BusinessStatus,
} from "@/lib/api";

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
};

const EMPTY: Form = {
  name: "", city_id: "", category: "restaurant", area: "", maps_url: "", latitude: "", longitude: "", phone: "", whatsapp: "",
  telegram: "", website: "", description: "", about: "", hours: "", languages: [], verified: false,
};

const toForm = (b: Business): Form => ({
  id: b.id, name: b.name, city_id: String(b.city_id), category: b.category, area: b.area, maps_url: b.maps_url ?? "",
  latitude: b.latitude?.toString() ?? "", longitude: b.longitude?.toString() ?? "", phone: b.phone ?? "", whatsapp: b.whatsapp ?? "",
  telegram: b.telegram ?? "", website: b.website ?? "", description: b.description ?? "", about: b.about ?? "", hours: b.hours ?? "",
  languages: b.languages ?? [], verified: b.verified,
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

  const run = (claim: BusinessClaimRow, action: "approve" | "reject") => {
    if (action === "approve" && !window.confirm(`Hand ${claim.business?.name} to ${claim.owner?.name}? They will be able to edit it; their edits still come to you for review.`)) return;
    decide.mutateAsync({ id: claim.id, action })
      .then(() => toast.success(action === "approve" ? "Handed over. The owner was told in Telegram." : "Claim rejected. The owner was told."))
      .catch((e: Error) => toast.error(e.message));
  };

  if (error) return <div className="text-destructive">{error.message}</div>;
  if (isLoading) return <div className="text-default-500">Loading…</div>;
  if (!data?.data.length) return <Card><CardContent className="p-6 text-default-500">No ownership claims waiting.</CardContent></Card>;

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
              <Button size="sm" color="success" disabled={decide.isPending} onClick={() => run(c, "approve")}>Hand over</Button>
              <Button size="sm" variant="outline" color="destructive" disabled={decide.isPending} onClick={() => run(c, "reject")}>Reject</Button>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
};

const DirectoryInner = () => {
  const router = useRouter();
  const params = useSearchParams();
  const status = (params.get("status") ?? "pending") as Tab;
  const cityId = params.get("city_id") ?? "";
  const category = params.get("category") ?? "";
  const search = params.get("search") ?? "";
  const page = Number(params.get("page") ?? "1");
  const [searchInput, setSearchInput] = useState(search);
  const [form, setForm] = useState<Form | null>(null);
  const [current, setCurrent] = useState<Business | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [note, setNote] = useState("");

  const query = new URLSearchParams({ status: status === "claims" ? "pending" : status, page: String(page) });
  if (cityId) query.set("city_id", cityId);
  if (category) query.set("category", category);
  if (search) query.set("search", search);

  const { data, isLoading, error } = useApiQuery<BusinessesPage>(["businesses", query.toString()], `/admin/businesses?${query.toString()}`);

  const invalidate = [["businesses"], ["dashboard"], ["directory-cities"]];
  const save = useApiMutation<{ business: Business }, Form>((req, f) => (f.id ? req(`/admin/businesses/${f.id}`, "PUT", toBody(f)) : req("/admin/businesses", "POST", toBody(f))), invalidate);
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
    setForm(b ? toForm(b) : { ...EMPTY, city_id: String(data?.choices.cities[0]?.id ?? "") });
  };

  const submit = () => {
    if (!form) return;
    setErrors({});
    save
      .mutateAsync(form)
      .then(() => {
        toast.success(form.id ? "Saved." : "Added, listed and posted to the channel.");
        setForm(null);
      })
      .catch((e: ApiError) => {
        setErrors(e.errors ?? {});
        toast.error(e.message);
      });
  };

  const decide = (action: string, done: string) => {
    if (!current) return;
    act
      .mutateAsync({ id: current.id, action, note: note || undefined })
      .then(() => {
        toast.success(done);
        setForm(null);
      })
      .catch((e: Error) => toast.error(e.message));
  };

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
            {error && <div className="p-4 text-destructive">{error.message}</div>}
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
                {isLoading && <TableRow><TableCell colSpan={7} className="text-default-500">Loading…</TableCell></TableRow>}
                {data?.data.length === 0 && (
                  <TableRow><TableCell colSpan={7} className="text-default-500">{status === "pending" ? "Nothing waiting for review." : status === "changes" ? "No owner edits waiting." : "Nothing here."}</TableCell></TableRow>
                )}
                {data?.data.map((b) => (
                  <TableRow key={b.id} className="cursor-pointer" onClick={() => open(b)}>
                    <TableCell>
                      {b.pending_photo || b.photo ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={(b.pending_photo ?? b.photo) as string} alt="" className="h-10 w-14 rounded object-cover" />
                      ) : <div className="h-10 w-14 rounded bg-default-100" />}
                    </TableCell>
                    <TableCell className="max-w-xs">
                      <div className="font-medium text-default-900">{b.name} {b.verified && <span title="Verified">✅</span>}</div>
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
                    <TableCell className="text-sm">{b.city}</TableCell>
                    <TableCell className="text-sm font-mono whitespace-nowrap">{b.phone ?? "—"}</TableCell>
                    <TableCell className="text-sm">
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

      <Dialog open={form !== null} onOpenChange={(o) => !o && setForm(null)}>
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
                    <Button size="sm" color="success" disabled={act.isPending} onClick={() => decide("changes/approve", "Changes are live; the channel post was updated and the owner told.")}>Approve changes</Button>
                    <Button size="sm" variant="outline" color="destructive" disabled={act.isPending} onClick={() => decide("changes/reject", "Changes rejected; the owner was told.")}>Reject changes</Button>
                  </div>
                </div>
              )}

              <div className="grid gap-4 md:grid-cols-2">
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">Business name *</Label>
                  <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                  {err("name")}
                </div>
                <div>
                  <Label className="mb-1.5 block">City *</Label>
                  <Select value={form.city_id} onValueChange={(v) => setForm({ ...form, city_id: v })}>
                    <SelectTrigger><SelectValue placeholder="Pick a city" /></SelectTrigger>
                    <SelectContent>{data?.choices.cities.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.name_en}{!c.active && " (off)"}</SelectItem>)}</SelectContent>
                  </Select>
                  {err("city_id")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Kind *</Label>
                  <Select value={form.category} onValueChange={(v) => setForm({ ...form, category: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{Object.entries(data?.choices.categories ?? {}).map(([k, label]) => <SelectItem key={k} value={k}>{label}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">Location: area or street *</Label>
                  <Input value={form.area} onChange={(e) => setForm({ ...form, area: e.target.value })} placeholder="Deira, near Al Rigga metro" />
                  {err("area")}
                </div>
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">Google Maps link</Label>
                  <Input value={form.maps_url} onChange={(e) => setForm({ ...form, maps_url: e.target.value })} placeholder="https://maps.app.goo.gl/…" />
                  <p className="text-xs text-default-500 mt-1">A link with coordinates in it fills the pin below on save.</p>
                  {err("maps_url")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Latitude</Label>
                  <Input value={form.latitude} onChange={(e) => setForm({ ...form, latitude: e.target.value })} placeholder="25.2697" />
                  {err("latitude")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Longitude</Label>
                  <Input value={form.longitude} onChange={(e) => setForm({ ...form, longitude: e.target.value })} placeholder="55.3095" />
                  {err("longitude")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Phone</Label>
                  <Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="04 123 4567" />
                  {err("phone")}
                </div>
                <div>
                  <Label className="mb-1.5 block">WhatsApp</Label>
                  <Input value={form.whatsapp} onChange={(e) => setForm({ ...form, whatsapp: e.target.value })} placeholder="050 123 4567" />
                  {err("whatsapp")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Telegram</Label>
                  <Input value={form.telegram} onChange={(e) => setForm({ ...form, telegram: e.target.value })} placeholder="@username" />
                  {err("telegram")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Website</Label>
                  <Input value={form.website} onChange={(e) => setForm({ ...form, website: e.target.value })} placeholder="https://…" />
                  {err("website")}
                </div>
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">One line about it</Label>
                  <Textarea rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                  {err("description")}
                </div>
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">About (shown on its page)</Label>
                  <Textarea rows={4} value={form.about} onChange={(e) => setForm({ ...form, about: e.target.value })} />
                  {err("about")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Opening hours</Label>
                  <Input value={form.hours} onChange={(e) => setForm({ ...form, hours: e.target.value })} placeholder="Every day 10:00–23:00" />
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

              {current && current.reports && current.reports.length > 0 && (
                <div className="text-sm">
                  <div className="text-default-500 mb-1">Reports of wrong information</div>
                  <ul>{current.reports.map((r, i) => <li key={i}>{r.member ?? "A member"} · {formatDateTime(r.at)}</li>)}</ul>
                </div>
              )}

              {current && current.status !== "approved" && !current.changes_submitted_at && (
                <div>
                  <Label className="mb-1.5 block">Reason (sent to the owner with a rejection)</Label>
                  <Input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Closed down, or could not confirm" />
                </div>
              )}

              <DialogFooter className="flex-wrap gap-2">
                {current && current.status !== "approved" && (
                  <Button color="success" disabled={act.isPending} onClick={() => decide("approve", current.status === "hidden" ? "Listed again; reports cleared." : "Approved: live on the website and posted to the channel.")}>
                    {current.status === "hidden" ? "List again" : "Approve"}
                  </Button>
                )}
                {current && current.status === "pending" && (
                  <Button color="destructive" variant="outline" disabled={act.isPending} onClick={() => decide("reject", "Rejected.")}>Reject</Button>
                )}
                {current && current.status === "approved" && (
                  <Button color="warning" variant="outline" disabled={act.isPending} onClick={() => decide("hide", "Hidden from the directory and removed from the channel.")}>Hide</Button>
                )}
                {current && (
                  <Button color="destructive" variant="ghost" disabled={act.isPending} onClick={() => window.confirm(`Delete ${current.name} for good?`) && decide("delete", "Deleted.")}>Delete</Button>
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
