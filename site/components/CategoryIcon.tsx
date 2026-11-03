import {
  BookOpen, Briefcase, Building2, Car, Coffee, FileText, HardHat, Hotel, Landmark, Package, PartyPopper, Plane, Scissors,
  Shield, Shirt, ShoppingBasket, Sparkles, Stethoscope, Store, Truck, Utensils, Wrench, type LucideIcon,
} from "lucide-react";

/** Directory kinds → an icon in the same family as the rest of the interface. */
const BUSINESS: Record<string, LucideIcon> = {
  restaurant: Utensils,
  cafe: Coffee,
  grocery: ShoppingBasket,
  salon: Scissors,
  cargo: Package,
  travel: Plane,
  typing: FileText,
  clinic: Stethoscope,
  fashion: Shirt,
  events: PartyPopper,
  worship: Landmark,
  education: BookOpen,
  services: Wrench,
  other: Store,
};

/** Kinds of work → an icon. */
const TRADE: Record<string, LucideIcon> = {
  domestic: Sparkles,
  driver: Car,
  security: Shield,
  cleaning: Sparkles,
  hospitality: Hotel,
  salon: Scissors,
  retail: ShoppingBasket,
  warehouse: Truck,
  nursing: Stethoscope,
  office: Building2,
  construction: HardHat,
  other: Briefcase,
};

export function BusinessIcon({ category, className = "size-5" }: { category: string; className?: string }) {
  const Icon = BUSINESS[category] ?? Store;
  return <Icon className={className} aria-hidden="true" strokeWidth={1.8} />;
}

export function TradeIcon({ category, className = "size-5" }: { category: string; className?: string }) {
  const Icon = TRADE[category] ?? Briefcase;
  return <Icon className={className} aria-hidden="true" strokeWidth={1.8} />;
}
