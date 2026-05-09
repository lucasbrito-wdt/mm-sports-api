# Order Details Header Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement an "Elite Minimalism" style Order Details Header component and integrate it into the PedidoDetalhePage.

**Architecture:** Pure component pattern for the header, receiving order data and providing action callbacks.

**Tech Stack:** Next.js, Tailwind CSS, Shadcn UI, Lucide React, Vitest.

---

### Task 1: Component & Test Scaffold

**Files:**
- Create: `features/commerce/components/order-details-header.tsx`
- Create: `features/commerce/components/order-details-header.test.tsx`

- [ ] **Step 1: Write the failing test**

```typescript
import { render, screen } from "@testing-library/react";
import { OrderDetailsHeader } from "./order-details-header";
import { describe, it, expect } from "vitest";

const mockOrder = {
  id: "order_1234567890",
  status: "paid",
  created_at: "2025-05-20T10:00:00Z",
  grand_total: "150.00",
  user: { name: "Lucas Brito", email: "lucas@example.com" }
} as any;

describe("OrderDetailsHeader", () => {
  it("renders order ID and status", () => {
    render(<OrderDetailsHeader order={mockOrder} />);
    expect(screen.getByText(/#order_123/i)).toBeDefined();
    expect(screen.getByText(/Pago/i)).toBeDefined();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test features/commerce/components/order-details-header.test.tsx` (or equivalent)
Expected: FAIL (Component not found)

- [ ] **Step 3: Implement minimal component**

```tsx
import { OrderRecord } from "../orders.schema";
import { Badge } from "@/components/ui/badge";
import { shortId } from "@/features/tracking/format";

interface OrderDetailsHeaderProps {
  order: OrderRecord;
}

export function OrderDetailsHeader({ order }: OrderDetailsHeaderProps) {
  return (
    <div className="flex justify-between items-center py-6">
      <div>
        <h1 className="text-2xl font-bold">#{shortId(order.id)}</h1>
        <Badge>Pago</Badge>
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test features/commerce/components/order-details-header.test.tsx`
Expected: PASS

- [ ] **Step 5: Commit scaffold**

```bash
git add features/commerce/components/order-details-header.tsx features/commerce/components/order-details-header.test.tsx
git commit -m "test(commerce): scaffold order details header"
```

---

### Task 2: Elite Style Implementation

**Files:**
- Modify: `features/commerce/components/order-details-header.tsx`

- [ ] **Step 1: Enhance visual design with Tailwind and Icons**

```tsx
import { ArrowLeft, RotateCcw, Truck, Calendar, CreditCard, User } from "lucide-react";
import Link from "next/link";
import { OrderRecord } from "../orders.schema";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { shortId } from "@/features/tracking/format";

interface OrderDetailsHeaderProps {
  order: OrderRecord;
  onRefund?: () => void;
  onManageShipping?: () => void;
}

const statusColors: Record<string, { bg: string, text: string, label: string }> = {
  paid: { bg: "bg-emerald-100/50", text: "text-emerald-700", label: "Pago" },
  shipped: { bg: "bg-blue-100/50", text: "text-blue-700", label: "Enviado" },
  cancelled: { bg: "bg-red-100/50", text: "text-red-700", label: "Cancelado" },
  pending_payment: { bg: "bg-amber-100/50", text: "text-amber-700", label: "Aguardando pagamento" },
};

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString("pt-BR", {
    dateStyle: "medium",
  });
}

export function OrderDetailsHeader({ order, onRefund, onManageShipping }: OrderDetailsHeaderProps) {
  const status = statusColors[order.status] || { bg: "bg-gray-100", text: "text-gray-700", label: order.status };

  return (
    <div className="flex flex-col gap-6 py-8 border-b border-border/40 bg-background/50 backdrop-blur-sm sticky top-0 z-10 -mx-6 px-6">
      <div className="flex items-center justify-between">
        <div className="flex flex-col gap-2">
          <Link href="/pedidos" className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors group">
            <ArrowLeft className="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
            <span>Voltar para Pedidos</span>
          </Link>
          <div className="flex items-center gap-4">
            <h1 className="text-3xl font-bold tracking-tight">#{shortId(order.id)}</h1>
            <Badge variant="outline" className={`${status.bg} ${status.text} border-transparent px-3 py-1 text-xs font-semibold flex items-center gap-1.5`}>
              <span className={`w-1.5 h-1.5 rounded-full ${status.text.replace('text-', 'bg-')}`} />
              {status.label}
            </Badge>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Button variant="outline" size="sm" onClick={onRefund} className="hover:scale-105 transition-transform">
            <RotateCcw className="w-4 h-4 mr-2" />
            Reembolsar
          </Button>
          <Button size="sm" onClick={onManageShipping} className="hover:scale-105 transition-transform">
            <Truck className="w-4 h-4 mr-2" />
            Gerenciar Envio
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm text-muted-foreground">
        <div className="flex items-center gap-2">
          <Calendar className="w-4 h-4" />
          <span>{formatDate(order.created_at)}</span>
        </div>
        <div className="flex items-center gap-2">
          <CreditCard className="w-4 h-4" />
          <span>{order.payment_method || "Cartão de Crédito"}</span>
        </div>
        <div className="flex items-center gap-2">
          <User className="w-4 h-4" />
          <span>{order.user?.name || order.user?.email || "Cliente"}</span>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Update tests to verify enhanced design**

```typescript
// Update tests in features/commerce/components/order-details-header.test.tsx
// Ensure it checks for labels like "Reembolsar", "Gerenciar Envio" and customer info.
```

- [ ] **Step 3: Commit enhanced component**

```bash
git add features/commerce/components/order-details-header.tsx
git commit -m "feat(commerce): add elite order details header styling"
```

---

### Task 3: Integration

**Files:**
- Modify: `app/(dashboard)/pedidos/[id]/page.tsx`

- [ ] **Step 1: Import and inject OrderDetailsHeader**

```tsx
// Import OrderDetailsHeader
// Use it inside CrudShell or replace CrudShell's default title/description handling
```

- [ ] **Step 2: Remove redundant info from the main Summary Card if needed**

- [ ] **Step 3: Verify visually and via tests**

- [ ] **Step 4: Commit integration**

```bash
git add app/(dashboard)/pedidos/[id]/page.tsx
git commit -m "feat(commerce): integrate elite order details header"
```
