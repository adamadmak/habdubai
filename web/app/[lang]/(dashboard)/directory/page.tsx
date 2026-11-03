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
import { useApiQuery, useApiMutation, formatDateTime, ApiError, type Business, type BusinessesPage, type BusinessStatus } from "@/lib/api";

const TABS: { value: BusinessStatus | "all"; label: string }[] = [
  { value: "pending", label: "Suggested" },
  { value: "approved", label: "Listed" },
  { value: "hidden", label: "Hidden" },
  { value: "rejected", label: "Rejected" },
  { value: "all", label: "All" },
];

const STATUS_COLOR = { pending: "warning", approved: "success", hidden: "destructive", rejected: "secondary" } as const;
const STATUS_LABEL: Record<BusinessStatus, string> = { pending: "suggested", approved: "listed", hidden: "hidden", rejected: "rejected" };

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
  telegram: string;
  description: string;
  verified: boolean;
};

const EMPTY: Form = { name: "", city_id: "", category: "restaurant", area: "", maps_url: "", latitude: "", longitude: "", phone: "", telegram: "", description: "", verified: false };

const toForm = (b: Business): Form => ({
  id: b.id, name: b.name, city_id: String(b.city_id), category: b.category, area: b.area, maps_url: b.maps_url ?? "",
  latitude: b.latitude?.toString() ?? "", longitude: b.longitude?.toString() ?? "", phone: b.phone ?? "", telegram: b.telegram ?? "",
  description: b.description ?? "", verified: b.verified,
});

const toBody = (f: Form) => ({
  name: f.name.trim(),
  city_id: Number(f.city_id),
  category: f.category,
  area: f.area.trim(),
  maps_url: f.maps_url.trim() || null,
  latitude: f.latitude.trim() ? Number(f.latitude) : null,
  longitude: f.longitude.trim() ? Number(f.longitude) : null,
  phone: f.phone.trim() || null,
  telegram: f.telegram.trim() || null,
  description: f.description.trim() || null,
  verified: f.verified,
});

const DirectoryInner = () => {
  const router = useRouter();
  const params = useSearchParams();
  const status = (params.get("status") ?? "pending") as BusinessStatus | "all";
  const cityId = params.get("city_id") ?? "";
  const category = params.get("category") ?? "";
  const search = params.get("search") ?? "";
  const page = Number(params.get("page") ?? "1");
  const [searchInput, setSearchInput] = useState(search);
  const [form, setForm] = useState<Form | null>(null);
  const [current, setCurrent] = useState<Business | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [note, setNote] = useState("");

  const query = new URLSearchParams({ status, page: String(page) });
  if (cityId) query.set("city_id", cityId);
  if (category) query.set("category", category);
  if (search) query.set("search", search);

  const { data, isLoading, error } = useApiQuery<BusinessesPage>(["businesses", query.toString()], `/admin/businesses?${query.toString()}`);

  const invalidate = [["businesses"], ["dashboard"], ["directory-cities"]];
  const save = useApiMutation<{ business: Business }, Form>((req, f) => (f.id ? req(`/admin/businesses/${f.id}`, "PUT", toBody(f)) : req("/admin/businesses", "POST", toBody(f))), invalidate);
  const act = useApiMutation<{ business?: Business }, { id: number; action: "approve" | "reject" | "hide" | "delete"; note?: string }>(
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
        toast.success(form.id ? "Saved." : "Added and listed.");
        setForm(null);
      })
      .catch((e: ApiError) => {
        setErrors(e.errors ?? {});
        toast.error(e.message);
      });
  };

  const decide = (action: "approve" | "reject" | "hide" | "delete", done: string) => {
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
  const total = data ? Object.values(data.counts).reduce((s, n) => s + (n ?? 0), 0) : 0;

  return (
    <div>
      <PageTitle title="Business directory" description="Habesha businesses by city. Members suggest them in the bot; nothing is listed until a moderator approves it.">
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
            {data && <span className="ml-1.5 opacity-75">{tab.value === "all" ? total : data.counts[tab.value] ?? 0}</span>}
          </button>
        ))}
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
      </div>

      <Card>
        <CardContent className="p-0">
          {error && <div className="p-4 text-destructive">{error.message}</div>}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Business</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Kind</TableHead>
                <TableHead>City</TableHead>
                <TableHead>Location</TableHead>
                <TableHead>Phone</TableHead>
                <TableHead>Suggested by</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableRow><TableCell colSpan={7} className="text-default-500">Loading…</TableCell></TableRow>}
              {data?.data.length === 0 && <TableRow><TableCell colSpan={7} className="text-default-500">{status === "pending" ? "No suggestions waiting." : "Nothing here."}</TableCell></TableRow>}
              {data?.data.map((b) => (
                <TableRow key={b.id} className="cursor-pointer" onClick={() => open(b)}>
                  <TableCell className="max-w-xs">
                    <div className="font-medium text-default-900">{b.name} {b.verified && <span title="Verified">✅</span>}</div>
                    {b.description && <div className="text-xs text-default-500 truncate">{b.description}</div>}
                    {b.review_note && b.status !== "approved" && <div className="text-xs text-warning mt-1">{b.review_note}</div>}
                  </TableCell>
                  <TableCell>
                    <Badge color={STATUS_COLOR[b.status]} variant="soft">{STATUS_LABEL[b.status]}</Badge>
                    {b.reports_count > 0 && <Badge color="destructive" variant="outline" className="ml-1">{b.reports_count} report{b.reports_count === 1 ? "" : "s"}</Badge>}
                  </TableCell>
                  <TableCell className="text-sm whitespace-nowrap">{data.choices.categories[b.category] ?? b.category}</TableCell>
                  <TableCell className="text-sm">{b.city}</TableCell>
                  <TableCell className="text-sm max-w-[14rem]">
                    <div className="truncate">{b.area}</div>
                    <a href={b.map_link} target="_blank" rel="noreferrer" className="text-xs text-primary" onClick={(e) => e.stopPropagation()}>
                      {b.latitude !== null ? "📍 pin" : b.maps_url ? "map link" : "search map"}
                    </a>
                  </TableCell>
                  <TableCell className="text-sm font-mono whitespace-nowrap">{b.phone ?? "—"}</TableCell>
                  <TableCell className="text-sm">
                    {b.submitted_by?.name ?? <span className="text-default-500">admin</span>}
                    <div className="text-xs text-default-500">{formatDateTime(b.created_at)}</div>
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

      <Dialog open={form !== null} onOpenChange={(o) => !o && setForm(null)}>
        <DialogContent size="2xl" className="max-h-[90vh] overflow-y-auto">
          {form && (
            <>
              <DialogHeader>
                <DialogTitle>{form.id ? form.name || "Business" : "Add a business"}</DialogTitle>
                <DialogDescription>
                  {current?.submitted_by ? `Suggested by ${current.submitted_by.name} on ${formatDateTime(current.created_at)}.` : form.id ? "Added by an admin." : "Added here, it is listed at once."}
                </DialogDescription>
              </DialogHeader>

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
                  <Label className="mb-1.5 block">Phone or WhatsApp</Label>
                  <Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="04 123 4567" />
                  {err("phone")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Telegram</Label>
                  <Input value={form.telegram} onChange={(e) => setForm({ ...form, telegram: e.target.value })} placeholder="@username" />
                  {err("telegram")}
                </div>
                <div className="md:col-span-2">
                  <Label className="mb-1.5 block">One line about it</Label>
                  <Textarea rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                  {err("description")}
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

              {current && current.status !== "approved" && (
                <div>
                  <Label className="mb-1.5 block">Note (kept with a rejection)</Label>
                  <Input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Closed down, or could not confirm" />
                </div>
              )}

              <DialogFooter className="flex-wrap gap-2">
                {current && current.status !== "approved" && (
                  <Button color="success" disabled={act.isPending} onClick={() => decide("approve", current.status === "hidden" ? "Listed again; reports cleared." : "Approved and listed.")}>
                    {current.status === "hidden" ? "List again" : "Approve"}
                  </Button>
                )}
                {current && current.status === "pending" && (
                  <Button color="destructive" variant="outline" disabled={act.isPending} onClick={() => decide("reject", "Rejected.")}>Reject</Button>
                )}
                {current && current.status === "approved" && (
                  <Button color="warning" variant="outline" disabled={act.isPending} onClick={() => decide("hide", "Hidden from the directory.")}>Hide</Button>
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
