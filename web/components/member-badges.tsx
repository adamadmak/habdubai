import { Badge } from "@/components/ui/badge";
import type { Member } from "@/lib/api";

export const StatusBadge = ({ status }: { status: Member["status"] }) => {
  const color = status === "active" ? "success" : status === "onboarding" ? "info" : status === "banned" ? "destructive" : "secondary";
  const label = status === "onboarding" ? "joining" : status;
  return <Badge color={color} variant="soft" className="capitalize">{label}</Badge>;
};

export const RoleBadge = ({ role }: { role: Member["role"] }) => {
  if (role === "member") return <span className="text-default-500 text-sm">member</span>;
  const color = role === "admin" ? "destructive" : role === "moderator" ? "warning" : "info";
  return <Badge color={color} variant="outline" className="capitalize">{role}</Badge>;
};
