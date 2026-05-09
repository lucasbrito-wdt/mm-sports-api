# Design Spec: Elite Order Details Header

Refactor the Order Details header to an "Elite Minimalism" style (Stripe/Shopify inspired).

## 1. Overview
Create a high-end "Hero" header for the Order Details page to improve visual clarity and provide immediate access to key actions.

## 2. Component: `OrderDetailsHeader`

### 2.1. Location
`features/commerce/components/order-details-header.tsx`

### 2.2. Props
```typescript
interface OrderDetailsHeaderProps {
  order: OrderRecord;
  onRefund?: () => void;
  onManageShipping?: () => void;
}
```

### 2.3. Visual Structure (Elite Minimalism)
- **Top Row:** 
  - Left: Breadcrumb/Back button ("Pedidos" link).
  - Right: "Elite" Badge (Status) + Order ID.
- **Main Row (Hero):**
  - Left: Large bold `#ORDER_ID` (using `shortId`).
  - Right: Action Buttons Group (Refund, Manage Shipping).
- **Meta Row:**
  - Horizontal list of metadata with subtle icons:
    - Date created.
    - Payment Status summary.
    - Customer Info (Name/Email).

### 2.4. Styles & Interactions
- Use `Button` with `hover:scale-105 transition-transform` for subtle micro-interactions.
- Refined Status Badges: Use a dot-indicator pattern inside the badge for a more professional look.
- Clean spacing: `py-8` for the hero section.

## 3. Integration
- Modify `app/(dashboard)/pedidos/[id]/page.tsx`.
- Replace the current title/description in `CrudShell` (or move them inside the shell content if more appropriate for the "Hero" look).
- Inject the new `OrderDetailsHeader` at the top of the content area.

## 4. Testing Strategy
- File: `features/commerce/components/order-details-header.test.tsx`.
- Test cases:
  - Renders the order ID.
  - Displays the correct status label.
  - Action buttons are present and clickable.

## 5. Scope Check
- Focused on the header component and its immediate integration.
- Does not modify existing order status logic or API.
