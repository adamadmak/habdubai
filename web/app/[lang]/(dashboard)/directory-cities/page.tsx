"use client";
import { useState } from "react";
import Link from "next/link";
import toast from "react-hot-toast";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import PageTitle from "@/components/page-title";
import ConfirmButton from "@/components/confirm-button";
import { EmptyState, ErrorState, LoadingRows, TableMessage } from "@/components/states";
import { toastResult } from "@/lib/toast";
import { MapPin } from "lucide-react";
import { useApiQuery, useApiMutation, ApiError, errorText, type DirectoryCity } from "@/lib/api";

type Form = { id?: number; name_en: string; name_am: string; name_ti: string; name_om: string; country: string; sort: number; active: boolean };

const EMPTY: Form = { name_en: "", name_am: "", name_ti: "", name_om: "", country: "AE", sort: 100, active: true };

const CitiesPage = () => {
  const { data, isLoading, error, refetch } = useApiQuery<{ data: DirectoryCity[] }>(["directory-cities"], "/admin/directory-cities");
  const [form, setForm] = useState<Form | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const invalidate = [["directory-cities"], ["businesses"]];
  const save = useApiMutation<unknown, Form>((req, f) => (f.id ? req(`/admin/directory-cities/${f.id}`, "PUT", f) : req("/admin/directory-cities", "POST", f)), invalidate);
  const remove = useApiMutation<unknown, number>((req, id) => req(`/admin/directory-cities/${id}`, "DELETE"), invalidate);

  const submit = () => {
    if (!form) return;
    setErrors({});
    save
      .mutateAsync({ ...form, country: form.country.toUpperCase() })
      .then(() => {
        toast.success(form.id ? "City saved." : "City added.");
        setForm(null);
      })
      .catch((e: ApiError) => {
        setErrors(e.errors ?? {});
        toast.error(errorText(e));
      });
  };

  const err = (k: string) => errors[k] && <p className="text-xs text-destructive mt-1">{errors[k][0]}</p>;

  return (
    <div>
      <PageTitle title="Directory cities" description="The cities members pick from, in the order shown. Each name appears on the bot's buttons in the member's language; Afaan Oromoo falls back to English when empty.">
        <Button onClick={() => { setErrors({}); setForm({ ...EMPTY }); }}>Add city</Button>
      </PageTitle>

      <Card>
        <CardContent className="p-0">
          {error && <ErrorState className="m-4" error={error} onRetry={() => refetch()} title="Cities could not be loaded" />}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>City</TableHead>
                <TableHead>Amharic</TableHead>
                <TableHead>Tigrinya</TableHead>
                <TableHead>Afaan Oromoo</TableHead>
                <TableHead>Country</TableHead>
                <TableHead>Order</TableHead>
                <TableHead>Listed</TableHead>
                <TableHead>Suggested</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading && <TableMessage colSpan={9}><LoadingRows className="p-4" /></TableMessage>}
              {data?.data.length === 0 && (
                <TableMessage colSpan={9}>
                  <EmptyState icon={MapPin} title="No cities yet" compact action={<Button size="sm" onClick={() => { setErrors({}); setForm({ ...EMPTY }); }}>Add city</Button>}>
                    Members browse the directory by city first. The eight UAE cities are normally seeded; add them here if this database is new.
                  </EmptyState>
                </TableMessage>
              )}
              {data?.data.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="font-medium">
                    {c.name_en} {!c.active && <Badge color="secondary" variant="soft" className="ml-1">off</Badge>}
                  </TableCell>
                  <TableCell>{c.name_am}</TableCell>
                  <TableCell>{c.name_ti}</TableCell>
                  <TableCell>{c.name_om || <span className="text-default-400">English</span>}</TableCell>
                  <TableCell>{c.country}</TableCell>
                  <TableCell>{c.sort}</TableCell>
                  <TableCell><Link className="text-primary" href={`/directory?status=approved&city_id=${c.id}`}>{c.listed_count ?? 0}</Link></TableCell>
                  <TableCell><Link className="text-primary" href={`/directory?status=pending&city_id=${c.id}`}>{c.pending_count ?? 0}</Link></TableCell>
                  <TableCell className="text-right whitespace-nowrap space-x-2">
                    <Button size="sm" variant="outline" color="secondary" onClick={() => { setErrors({}); setForm({ id: c.id, name_en: c.name_en, name_am: c.name_am, name_ti: c.name_ti, name_om: c.name_om ?? "", country: c.country, sort: c.sort, active: c.active }); }}>Edit</Button>
                    {(c.listed_count ?? 0) + (c.pending_count ?? 0) === 0 && (
                      <ConfirmButton size="sm" variant="outline" color="destructive" label="Delete"
                        title={`Delete ${c.name_en}?`}
                        description="It has no listed or waiting businesses. To keep it but hide it from the bot, turn it off instead."
                        confirmLabel="Delete" onConfirm={() => toastResult(remove.mutateAsync(c.id), "Deleted.")} />
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={form !== null} onOpenChange={(o) => !o && setForm(null)}>
        <DialogContent size="lg">
          {form && (
            <>
              <DialogHeader>
                <DialogTitle>{form.id ? `Edit ${form.name_en}` : "Add a city"}</DialogTitle>
                <DialogDescription>A city that is off stays in the database with its businesses but disappears from the bot.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 md:grid-cols-3">
                <div>
                  <Label className="mb-1.5 block">English *</Label>
                  <Input value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} placeholder="Riyadh" />
                  {err("name_en")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Amharic *</Label>
                  <Input value={form.name_am} onChange={(e) => setForm({ ...form, name_am: e.target.value })} placeholder="ሪያድ" />
                  {err("name_am")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Tigrinya *</Label>
                  <Input value={form.name_ti} onChange={(e) => setForm({ ...form, name_ti: e.target.value })} placeholder="ሪያድ" />
                  {err("name_ti")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Afaan Oromoo</Label>
                  <Input value={form.name_om} onChange={(e) => setForm({ ...form, name_om: e.target.value })} placeholder="Riyaad (English when empty)" />
                  {err("name_om")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Country code</Label>
                  <Input value={form.country} maxLength={2} onChange={(e) => setForm({ ...form, country: e.target.value })} />
                  {err("country")}
                </div>
                <div>
                  <Label className="mb-1.5 block">Order</Label>
                  <Input type="number" value={form.sort} onChange={(e) => setForm({ ...form, sort: Number(e.target.value) })} />
                </div>
                <label className="flex items-end gap-2 pb-2 text-sm">
                  <Switch checked={form.active} onCheckedChange={(v) => setForm({ ...form, active: v })} />
                  Shown in the bot
                </label>
              </div>
              <DialogFooter>
                <Button disabled={save.isPending} onClick={submit}>{form.id ? "Save" : "Add city"}</Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default CitiesPage;
