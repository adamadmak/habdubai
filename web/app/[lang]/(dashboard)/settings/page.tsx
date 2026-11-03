"use client";
import { useState } from "react";
import toast from "react-hot-toast";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import PageTitle from "@/components/page-title";
import { useApiQuery, useApiMutation, formatDateTime, type SettingsPayload } from "@/lib/api";

const FIELDS: { key: string; label: string; hint: string; placeholder?: string; secret?: boolean }[] = [
  { key: "public_url", label: "Public URL of the API", hint: "Where Telegram reaches the webhook, and the address of listing photos. Must be https and not localhost.", placeholder: "https://api.habesha-dubai.com" },
  { key: "site_url", label: "Public website address", hint: "The website members and visitors use. Channel posts and bot messages link to it.", placeholder: "https://habesha-dubai.com" },
  { key: "support_telegram_username", label: "Support Telegram username", hint: "Shown to a member whose number is already taken, and in the ban message.", placeholder: "habesha_admin" },
  { key: "allowed_phone_countries", label: "Allowed phone country codes", hint: "Comma separated. A member outside these is accepted but marked for review.", placeholder: "971,251" },
  { key: "telegram_channel_link", label: "Public channel invite link", hint: "Sent at the end of joining.", placeholder: "https://t.me/habesha_dubai" },
  { key: "telegram_questions_group_link", label: "Questions group invite link", hint: "Sent at the end of joining.", placeholder: "https://t.me/+…" },
  { key: "telegram_goods_group_link", label: "Goods board group invite link", hint: "Sent at the end of joining.", placeholder: "https://t.me/+…" },
  { key: "telegram_questions_group_id", label: "Questions group chat id", hint: "Numeric id (starts with -100). Needed to remove a member who leaves or is banned.", placeholder: "-1001234567890" },
  { key: "telegram_goods_group_id", label: "Goods board group chat id", hint: "Same, for the goods group.", placeholder: "-1001234567890" },
  { key: "community_language", label: "Language of the bot's labels in groups", hint: "am, ti or en. Members' own words stay as they wrote them.", placeholder: "am" },
  { key: "questions_per_day", label: "Questions a member may ask per day", hint: "Default 5.", placeholder: "5" },
  { key: "assistant_per_day", label: "Assistant answers per member per day", hint: "Each answer is one Claude call. Default 20; 0 turns the assistant off.", placeholder: "20" },
  { key: "safety_consulate_phone", label: "Ethiopian consulate phone, for /safe", hint: "Shown on the safety card when set. Check the number before saving.", placeholder: "+971 …" },
  { key: "safety_mohre_phone", label: "MoHRE phone, for /safe", hint: "Shown on the safety card when set. Check the number before saving.", placeholder: "…" },
  { key: "tick_token", label: "Scheduler token (TICK_TOKEN)", hint: "The same value as TICK_TOKEN on the public website, which calls the scheduler every minute. At least 32 characters. Write-only.", secret: true },
  { key: "site_api_key", label: "Website key (SITE_API_KEY)", hint: "The same value as SITE_API_KEY on the public website, so rate limits count each visitor. At least 32 characters. Write-only.", secret: true },
];

const SettingsPage = () => {
  // No refetch on focus: coming back to the tab must not disturb a half-typed form.
  const { data, isLoading, error } = useApiQuery<SettingsPayload>(["settings"], "/admin/settings", { refetchOnWindowFocus: false });
  // null until the admin types: until then the form shows what the server has, and after
  // that a refetch never overwrites the draft.
  const [form, setForm] = useState<Record<string, string> | null>(null);
  const [token, setToken] = useState("");

  const save = useApiMutation<SettingsPayload, Record<string, string>>((request, body) => request("/admin/settings", "PUT", body), [["settings"], ["dashboard"]]);
  const saveToken = useApiMutation<{ bot: { username?: string }; webhook_error: string | null }, string>((request, t) => request("/admin/settings/telegram", "POST", { token: t }), [["settings"], ["dashboard"]]);
  const setWebhook = useApiMutation<unknown>((request) => request("/admin/settings/webhook", "POST"), [["settings"]]);

  if (error) return <div className="text-destructive">{error.message}</div>;
  if (isLoading || !data) return <div className="text-default-600">Loading…</div>;

  const telegram = data.telegram;
  const values = form ?? data.settings;
  const edit = (key: string, value: string) => setForm((current) => ({ ...(current ?? data.settings), [key]: value }));

  return (
    <div>
      <PageTitle title="Settings" description="Operator values live in the database, so they survive a deploy and never depend on environment variables." />

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">Community</CardTitle>
          </CardHeader>
          <CardContent>
            <form
              className="space-y-5"
              onSubmit={(e) => {
                e.preventDefault();
                save
                  .mutateAsync(values)
                  .then((saved) => {
                    // What the server now holds; the write-only boxes come back empty.
                    setForm(saved.settings);
                    toast.success("Settings saved.");
                  })
                  .catch((err) => toast.error(err.message));
              }}
            >
              {FIELDS.map((f) => (
                <div key={f.key}>
                  <Label htmlFor={f.key} className="mb-2 block">{f.label}</Label>
                  {/* "off" is ignored for passwords; "new-password" and the manager hints keep the admin's own login out of write-only boxes. */}
                  <Input
                    id={f.key}
                    type={f.secret ? "password" : "text"}
                    autoComplete={f.secret ? "new-password" : undefined}
                    data-1p-ignore={f.secret ? true : undefined}
                    data-lpignore={f.secret ? "true" : undefined}
                    value={values[f.key] ?? ""}
                    placeholder={f.secret ? (data.secrets?.[f.key] ? "Saved. Type a new value to replace it." : "Not set") : f.placeholder}
                    onChange={(e) => edit(f.key, e.target.value)}
                  />
                  <p className="mt-1 text-xs text-default-500">{f.hint}</p>
                  {save.error?.errors?.[f.key] && <p className="mt-1 text-xs text-destructive">{save.error.errors[f.key][0]}</p>}
                </div>
              ))}
              <Button type="submit" disabled={save.isPending}>Save settings</Button>
            </form>
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center justify-between">
                Telegram bot
                {telegram.configured ? <Badge color="success" variant="soft">@{telegram.username || "set"}</Badge> : <Badge color="destructive" variant="soft">not set</Badge>}
              </CardTitle>
              <CardDescription>The token from BotFather. It is checked with Telegram, stored encrypted, and never shown again.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <form
                className="space-y-3"
                onSubmit={(e) => {
                  e.preventDefault();
                  saveToken
                    .mutateAsync(token)
                    .then((r) => {
                      setToken("");
                      if (r.webhook_error) {
                        toast.success(`Token saved for @${r.bot.username ?? "bot"}. Webhook not registered: ${r.webhook_error}`, { duration: 8000 });
                      } else {
                        toast.success(`Token saved for @${r.bot.username ?? "bot"} and the webhook registered.`);
                      }
                    })
                    .catch((err) => toast.error(err.message));
                }}
              >
                <Input type="password" value={token} onChange={(e) => setToken(e.target.value)} placeholder="123456789:ABC…" autoComplete="new-password" data-1p-ignore data-lpignore="true" />
                <Button type="submit" className="w-full" disabled={saveToken.isPending || token.trim() === ""}>Save token and register webhook</Button>
              </form>

              <div className="text-sm space-y-1">
                <div className="flex justify-between"><span className="text-default-500">Webhook</span><span className="font-mono text-xs text-right break-all">{telegram.webhook?.url || "not registered"}</span></div>
                {telegram.webhook?.pending_update_count !== undefined && <div className="flex justify-between"><span className="text-default-500">Pending updates</span><span>{telegram.webhook.pending_update_count}</span></div>}
                {telegram.webhook?.last_error_message && <div className="text-destructive">Last error: {telegram.webhook.last_error_message} ({formatDateTime(telegram.webhook.last_error_date)})</div>}
                {telegram.webhook?.error && <div className="text-destructive">{telegram.webhook.error}</div>}
              </div>

              <Button variant="outline" className="w-full" disabled={!telegram.configured || setWebhook.isPending} onClick={() => setWebhook.mutateAsync().then(() => toast.success("Webhook registered.")).catch((err) => toast.error(err.message))}>
                Register the webhook again
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Groups and channels the bot is in</CardTitle>
              <CardDescription>
                Add the bot to a group or channel as an admin and it appears here. For questions, use a group with Topics turned on, so every question gets its own thread.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {(data.chats ?? []).length === 0 && <div className="text-sm text-default-500">None yet.</div>}
              {(data.chats ?? []).map((c) => (
                <div key={c.chat_id} className="rounded-md border border-default-200 p-3 text-sm space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-medium text-default-900">{c.title ?? c.chat_id}</span>
                    <span className="flex gap-1">
                      <Badge color="secondary" variant="soft">{c.type}</Badge>
                      {c.is_forum && <Badge color="success" variant="soft">topics</Badge>}
                      {c.bot_status !== "administrator" && <Badge color="warning" variant="soft">not admin</Badge>}
                    </span>
                  </div>
                  <div className="font-mono text-xs text-default-600">{c.chat_id}{c.username && ` · @${c.username}`}</div>
                  {c.type !== "channel" ? (
                    <div className="flex flex-wrap gap-2">
                      <Button size="xs" variant="outline" onClick={() => { edit("telegram_questions_group_id", String(c.chat_id)); toast.success("Set as the questions group; press Save settings."); }}>Use for questions</Button>
                      <Button size="xs" variant="outline" onClick={() => { edit("telegram_goods_group_id", String(c.chat_id)); toast.success("Set as the goods group; press Save settings."); }}>Use for goods</Button>
                    </div>
                  ) : (
                    <div className="text-xs text-default-500">A channel: set it as the jobs channel on Job settings{c.username ? ` (@${c.username})` : ` (${c.chat_id})`}.</div>
                  )}
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default SettingsPage;
