# JWB Checkout — Installation & Usage Guide

## Overview

The JWB Checkout plugin adds three purchase flows to your WooCommerce + LearnDash site, and replaces several legacy WPCode snippets with a single managed plugin.

1. **Group Study** — When someone buys a group study seat, a unique 50%-off coupon is automatically generated and shown on the thank-you page and in the order confirmation email. Group members use this code to purchase individual courses at half price.

2. **Multi-Course** — When a customer adds two or more courses to their cart (same type: both group-study, or both gift), the second and every additional course is automatically discounted 50%. No coupon code needed — the price adjusts in real time.

3. **Gift Course** — Any product in the `gift` category follows the gift flow. The buyer enters the recipient's name and email at checkout; the plugin creates their account, enrolls them (via the linked individual-study or session sibling), and emails them their login credentials automatically.

4. **Purchase-Type Toggle** — A tab strip at the top of checkout lets customers switch between Individual Purchase and Gift Purchase in one click. Only shown for individual-study, session, and gift carts — group-study carts have no toggle.

---

## WPCode Snippets — What to Disable

The following WPCode snippets are **fully replaced by this plugin** and must be **disabled** after the plugin is activated:

- **Checkout page Hook** — replaced by `jwb_render_purchase_type_toggle` and `jwb_render_recipient_fields`
- **Checkout page CSS** — replaced by `assets/css/checkout.css`
- **Contact Form JS** — replaced by `assets/js/checkout.js`

The following snippets are **unrelated to this plugin** and should be **left active**:

- **Shipping Field** snippet
- **My Account Page Hooks** snippet

---

## Requirements

- WordPress 6.0 or later
- WooCommerce 8.0 or later (tested up to 10.6; High Performance Order Storage is supported)
- LearnDash 4.0 or later (required for course enrollment features)
- PHP 7.4 or later

---

## Installation

1. Upload the `jwb-checkout` folder to `/wp-content/plugins/`.
2. In your WordPress dashboard, go to **Plugins → Installed Plugins**.
3. Find **JWB Checkout** and click **Activate**.
4. Disable the three WPCode snippets listed above.

> If WooCommerce is not active, the plugin will refuse to activate and display an error.

---

## Required One-Time Setup

Complete these steps after activating the plugin. The plugin will not work correctly until all are done.

---

### Step 1 — Create the Four Product Categories

Go to **Products → Categories** and create the following four categories. The **slug** must be entered exactly as shown.

| Category Name    | Slug (must match exactly) | Use for |
|---|---|---|
| Group Study      | `group-study`      | Full group study course products |
| Individual Study | `individual-study` | Full individual course products |
| Session          | `session`          | Weekly session products for any course |
| Gift             | `gift`             | Gift versions of individual or session products |

> Each product should be assigned **exactly one** of these four categories.

> **How multi-course discounts work:** The 50% discount triggers automatically when a customer's cart contains 2 or more products of the same type (2+ group-study products, or 2+ gift products). Mixed carts do not trigger either discount.

> **Purchase-type toggle (admin rule):** To enable the Individual / Gift toggle at checkout, every sibling product in the same course family must share the same **Course Base Slug** value (see Step 4). Gift products must also have a **Paired Product** set (see Step 3).

---

### Step 2 — Create a Template Coupon for Each Group Study Product

Each group study product requires its own template coupon. When an order completes, the plugin clones the template for that specific product to generate the group member code.

**How to find a product's ID:** Go to **Products**, hover over the product row — the ID appears in the URL shown in the browser status bar (e.g. `post=19722` means the ID is `19722`).

**For each group study product:**

1. Go to **WooCommerce → Coupons → Add Coupon**.
2. Set the coupon code to: **`jwb_group_template_{product_id}`** — replacing `{product_id}` with the actual numeric ID of the product.
   - Example: if the Mary Heart Group product has ID `245`, the coupon code must be exactly **`jwb_group_template_245`**.
3. Configure it as follows:

| Field | Value |
|---|---|
| Discount type | Percentage discount |
| Coupon amount | 50 |
| Usage limit per coupon | Leave blank — the plugin sets this automatically |
| Individual use only | Your preference |
| Allowed categories | Add **`individual-study`** and **`session`** |
| Exclude products / categories | Add the `group-study` category |

4. Click **Publish**.

Repeat this for every group study product on the site.

> These coupons are **never used directly by customers**. They are templates only.

---

### Step 3 — Configure Gift Products

Gift products live in the `gift` category. They do **not** need LearnDash courses or groups linked directly — enrollment is resolved via the paired product.

1. Go to **Products → Add New** (or edit an existing product).
2. Assign the product to the **Gift** category.
3. Set the **Course Base Slug** field (see Step 4) so the toggle can find non-gift siblings.
4. Set the **Paired Product** field (in the General tab) to the individual-study or session product this gift represents. This is required for the checkout toggle and recipient enrollment.
5. Leave the LearnDash Courses / LearnDash Groups fields **blank** on the gift product — those fields live on the paired individual-study or session product only.
6. Click **Publish** / **Update**.

---

### Step 4 — Set the Course Base Slug on Every Managed Product

The **Course Base Slug** field (in the product General tab) is required for:
- The Individual / Gift checkout toggle
- Resolving which sibling's LearnDash courses to enroll a gift recipient in

**Rule:** every sibling product in the same course family must have the **same** Course Base Slug value.

Example — "Having a Mary Heart in a Martha World" has four products:

| Product | Category | Course Base Slug |
|---|---|---|
| Having a Mary Heart – Individual | `individual-study` | `having-a-mary-heart` |
| Having a Mary Heart – Group | `group-study` | `having-a-mary-heart` |
| Having a Mary Heart – Week One | `session` | `having-a-mary-heart` |
| Having a Mary Heart – Week Two | `session` | `having-a-mary-heart` |
| Having a Mary Heart – Gift | `gift` | `having-a-mary-heart` |
| Having a Mary Heart – Week One Gift | `gift` | `having-a-mary-heart` |
| Having a Mary Heart – Week Two Gift | `gift` | `having-a-mary-heart` |

> Only the `individual-study` and `session` products need LearnDash Courses / Groups assigned. The gift product uses the sibling's mapping automatically.

---

## How Each Feature Works

### Group Study Flow

- Customer purchases one or more `group-study` products.
- On order **Completed**, the plugin generates a unique 8-character coupon code for **each group-study product** purchased, cloned from that product's template coupon (`jwb_group_template_{product_id}`).
- Each code is shown on the **thank-you page** labeled with the product name, with a Copy button.
- All codes are included in the **order confirmation email**.
- **Usage limit:** set to the quantity of seats purchased.
- **Coupon expiry:** 2 years from the date of purchase.
- **Misuse prevention:** the generated code cannot be applied to a group-study product.

---

### Multi-Course Discount Flow

- Customer adds 2 or more `group-study` products, OR 2 or more `gift` products.
- The first item is charged at full price; every additional item of the same type is 50% off.
- A notice appears on the cart and checkout confirming the discount.
- No coupon code needed.
- Mixed carts do not trigger the discount.

> **woo-discount-rules compatibility:** This plugin hooks at priority 99 on `woocommerce_before_calculate_totals`, after woo-discount-rules (which runs at priority 10–20), so both apply correctly without conflict.

---

### Gift Course Recipient Fields

- When the cart contains one or more `gift` products, recipient name/email fields appear below the billing details — one set per gift item.
- The buyer is **not** enrolled in any gift course.
- Validation: name required, valid email required, cannot match buyer's billing email, no duplicates.
- On order **Completed**, for each recipient:
  - The plugin reads the gift product's **Paired Product** field to find the non-gift counterpart, then reads LearnDash mappings from that product.
  - Reads `_related_course` and `_related_group` from the paired product.
  - Creates a WordPress account (if none exists), enrolls the recipient, and emails them their credentials.
  - If no sibling is found, or the sibling has no LearnDash mapping, the recipient is skipped and a warning is logged.

---

### Purchase-Type Toggle

- A tab strip ("Individual Purchase" / "Gift Purchase") appears at the top of the checkout customer details section.
- Only shown for individual-study, session, and gift carts. Group-study carts have no toggle.
- Only shown when all cart items are the same plugin-managed type.
- Clicking a tab swaps all cart items to their sibling products of the target type simultaneously (AJAX, then page reload).
- If any sibling product cannot be resolved, the swap is aborted with an error message — no partial changes are made.

---

## Recipient Activation Email

New recipients receive an email with the subject: **[Your Site Name] | Your Course is Ready!**

The email includes login credentials, a Log In button, and a reminder to reset their password.

---

## Testing Checklist

**Group Study**
- [ ] Create template coupon `jwb_group_template_{product_id}` for each group-study product
- [ ] Place a test order for a group-study product, set to Completed
- [ ] Confirm coupon code appears on thank-you page with Copy button
- [ ] Confirm coupon code appears in the order email
- [ ] Place order with two different group-study products — confirm two separate codes
- [ ] Try generated code on a group-study product — confirm it's blocked
- [ ] Try generated code on an individual-study product — confirm it applies

**Multi-Course Discount**
- [ ] Add 2+ `group-study` products — confirm 2nd+ shows at 50% off
- [ ] Add 2+ `gift` products — confirm 2nd+ shows at 50% off
- [ ] Add a mix (group + individual) — confirm no discount
- [ ] Confirm discount notice on cart and checkout

**Gift / Recipient Flow**
- [ ] Set Course Base Slug on all sibling products; set Paired Product on the gift product
- [ ] Add gift product to cart — confirm recipient fields appear at checkout
- [ ] Test validation: blank name, invalid email, buyer's own email, duplicate emails
- [ ] Complete the order, set to Completed
- [ ] Confirm recipient account created in **Users**
- [ ] Confirm recipient enrolled in correct LearnDash course
- [ ] Confirm buyer is **not** enrolled
- [ ] Confirm activation email received

**Purchase-Type Toggle**
- [ ] Add an `individual-study` product to cart — confirm toggle shows with Individual active
- [ ] Click "Gift Purchase" — confirm cart swaps to `gift` sibling and recipient fields appear
- [ ] Click "Individual Purchase" — confirm cart swaps back
- [ ] Add a `session` product — confirm toggle shows Individual + Gift
- [ ] Add a `group-study` product — confirm no toggle appears
- [ ] Remove a sibling product and attempt swap — confirm error message, cart unchanged

---

## Troubleshooting

**Group coupon was not generated**
- Check that a template coupon named `jwb_group_template_{product_id}` exists and is published.
- Check logs: **WooCommerce → Status → Logs → jwb-checkout** for a `Template coupon not found` warning.
- Check that the product is assigned to the `group-study` category with slug `group-study`.

**Recipients were not processed**
- Check that the order status was set to **Completed** (not just Processing).
- Check that `_jwb_recipients` order meta was saved — visible under the order in **WooCommerce → Orders → [Order] → Custom Fields**.
- Check the logs for warnings about missing siblings or missing LearnDash mapping.

**Course enrollment not happening**
- Confirm LearnDash is active.
- Confirm the gift product has a **Paired Product** set in the General tab.
- Confirm the paired product has courses/groups in the LearnDash Courses / LearnDash Groups fields.
- Check logs for `Gift product has no paired product set` or `Paired product has no LearnDash courses or groups mapped` warnings.

**Toggle not appearing**
- Confirm all cart items are the same plugin-managed type (group-study, individual-study, session, or gift).
- Confirm the product has a Course Base Slug set.

**Toggle swap fails**
- Confirm the target sibling product exists, is published, and has the same Course Base Slug as the source product.
- Confirm the sibling is in the correct category for the direction requested.
- For gift swaps, confirm the gift product has a Paired Product set and that the paired product is published.

**Recipient fields not appearing at checkout**
- If you use **Checkout Field Editor Pro** (`woo-checkout-field-editor-pro`), confirm it has not disabled or repositioned the billing section.

**Recipient activation email not received**
- Check your site's email deliverability (an SMTP plugin like WP Mail SMTP is recommended).

**WooCommerce log location**
Go to **WooCommerce → Status → Logs** and select the `jwb-checkout` source from the dropdown.

---

## Notes for Your Developer

- The plugin is fully idempotent. If `woocommerce_order_status_completed` fires more than once, coupon generation and recipient processing each run only once (guarded by `_jwb_group_coupons_generated` and `_jwb_recipients_processed` order meta keys).
- The 50% price adjustment uses `set_price()` on cart items — it does not create or apply WooCommerce coupon records. Existing coupons stack normally.
- The purchase-type toggle AJAX (`jwb_swap_cart`) is all-or-nothing: all siblings are validated before any cart item is touched.
- All plugin activity is logged to the WooCommerce logger under source `jwb-checkout`.
- Text domain: `jwb-checkout`. All user-facing strings are translation-ready.
- HPOS compatible: all order meta uses the Order CRUD API (`$order->get_meta()` / `$order->update_meta_data()`).
