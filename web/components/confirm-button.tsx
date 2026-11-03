"use client";
import React, { useState } from "react";
import { Loader2 } from "lucide-react";
import { Button, type ButtonProps } from "@/components/ui/button";
import {
  AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

type Props = Omit<ButtonProps, "onClick"> & {
  /** The question, e.g. "Delete Habesha Kitchen?" */
  title: string;
  /** What happens, in one or two sentences. */
  description: React.ReactNode;
  /** The confirming button's label, e.g. "Delete". */
  confirmLabel: string;
  /** Runs on confirm; the dialog stays open (and busy) until it settles, and closes only on success. */
  onConfirm: () => Promise<unknown>;
  /** Extra fields shown in the dialog, e.g. a reason. */
  children?: React.ReactNode;
  label: React.ReactNode;
  tone?: "destructive" | "warning" | "default";
};

/**
 * A button that asks before it does something that cannot be undone. The
 * caller's onConfirm shows its own toast; a failure keeps the dialog open so
 * nothing typed into it is lost.
 */
const ConfirmButton = ({ title, description, confirmLabel, onConfirm, children, label, tone = "destructive", disabled, ...button }: Props) => {
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  const confirm = async () => {
    setBusy(true);
    try {
      await onConfirm();
      setOpen(false);
    } catch {
      // The caller has already said what went wrong.
    } finally {
      setBusy(false);
    }
  };

  return (
    <AlertDialog open={open} onOpenChange={(next) => !busy && setOpen(next)}>
      <AlertDialogTrigger asChild>
        <Button type="button" disabled={disabled} {...button}>{label}</Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        {children}
        <AlertDialogFooter className="gap-2">
          <AlertDialogCancel disabled={busy}>Cancel</AlertDialogCancel>
          {/* A plain button, not AlertDialogAction: the dialog must not close before the request settles. */}
          <Button type="button" color={tone === "default" ? "primary" : tone} disabled={busy} onClick={confirm}>
            {busy && <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />}
            {confirmLabel}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
};

export default ConfirmButton;
