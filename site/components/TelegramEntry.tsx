"use client";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { clearLaunchFromAddress, insideTelegram, rememberInsideTelegram, tellTelegram, telegramSignIn } from "@/lib/telegram-app";

/**
 * When the website opens inside Telegram (a button in the bot): it takes the
 * whole screen, shows the page's own colours in Telegram's bars, and signs the
 * member in with what Telegram signed, with no code to type. For every other
 * visitor this does nothing at all.
 */
export function TelegramEntry({ lang }: { lang: string }) {
  const router = useRouter();

  useEffect(() => {
    if (!insideTelegram()) return;
    rememberInsideTelegram();
    document.documentElement.dataset.telegram = "1";

    tellTelegram("web_app_ready");
    tellTelegram("web_app_expand");
    const dark = document.documentElement.dataset.theme === "dark";
    tellTelegram("web_app_set_header_color", { color: dark ? "#070c1f" : "#fbf8f2" });
    tellTelegram("web_app_set_background_color", { color: dark ? "#070c1f" : "#fbf8f2" });

    const signedIn = telegramSignIn(lang);
    clearLaunchFromAddress();
    void signedIn.then((status) => {
      if (status === "approved") {
        window.dispatchEvent(new Event("hd:signed-in"));
        router.refresh();
      }
    });
  }, [lang, router]);

  return null;
}
