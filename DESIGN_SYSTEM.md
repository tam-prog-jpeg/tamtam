# ZPPSU-IPO Design System Specification
**Project:** Web-Based Management System for ZPPSU-IPO with Application Monitoring  
**Design Reference:** Deli-Prop SaaS Dashboard Interface  
**Governing Agent Skills:** `frontend-design` & `ui-ux-kit` (Profile §B2 Dashboard/Admin/Data)  
**Version:** 2.0 (Modern SaaS Overhaul)

---

## 1. Design Vision & Foundations

This Design System serves as the canonical source of truth for all frontend interfaces in the ZPPSU-IPO system. It eliminates arbitrary decoration and AI-slop in favor of a high-trust, calm, data-dense interface tailored for institutional administration and IP management.

### The 60 / 30 / 10 Color Distribution
- **60% Dominant Base:** Clean light canvas (`#F8F9FB`) and pure white cards/surfaces (`#FFFFFF`).
- **30% Structure & Content:** Crisp borders (`#EAECF0`), dark primary typography (`#111827`), and muted structural labels (`#6B7280`).
- **10% Intentional Accent:** Royal Cobalt Blue (`#2563EB`) for primary CTAs, active states, and leading chart lines, paired with Coral Red (`#EF4444`) and Emerald Green (`#10B981`) for data semantics.

---

## 2. Color Palette & Tokens

### Neutrals & Surfaces
| Token Name | Hex Value | Usage |
| :--- | :--- | :--- |
| `--color-bg` | `#F8F9FB` | Global application canvas background |
| `--color-surface` | `#FFFFFF` | Cards, modals, topbar, sidebar surface |
| `--color-border` | `#EAECF0` | Dividers, card boundaries, table rules |
| `--color-border-subtle` | `#F2F4F7` | Inner card dividers, grid lines |
| `--color-text` | `#111827` | Headings, prominent metrics, body text |
| `--color-text-muted` | `#6B7280` | Secondary labels, timestamps, table th |
| `--color-text-subtle` | `#9CA3AF` | Inactive icons, placeholder text, axis ticks |

### Brand & Functional Accents
| Token Name | Hex Value | Background Tint | Meaning / Context |
| :--- | :--- | :--- | :--- |
| `--color-primary-600` | `#2563EB` | `#EFF6FF` (`primary-50`) | Primary buttons, active tabs, main chart series |
| `--color-danger` | `#EF4444` | `#FEF2F2` (`danger-bg`) | Pending/attention badges, secondary chart line, unread pills |
| `--color-success` | `#10B981` | `#ECFDF5` (`success-bg`) | Approved status, growth sparklines, positive percentages |
| `--color-warning` | `#F59E0B` | `#FFFBEB` (`warning-bg`) | Revisions needed, warnings, temporary hold |
| `--color-purple` | `#8B5CF6` | `#F5F3FF` (`purple-bg`) | User badges, tertiary gauge segments |

---

## 3. Typography Hierarchy

| Role | Font Family | Size | Weight | Line Height | Tracking |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Page Title** | Plus Jakarta Sans | `22px` | 700 | `1.2` | `-0.02em` |
| **Section Header (h3)** | Plus Jakarta Sans | `16px` | 700 | `1.3` | `-0.01em` |
| **KPI Metric Value** | Plus Jakarta Sans | `30px` | 700 | `1.0` | `-0.03em` |
| **Subheader / Eyebrow** | Inter | `11px` | 600 | `1.0` | `+0.06em` (Uppercase) |
| **Body Standard** | Inter | `13.5px`–`14px` | 400 / 500 | `1.5` | `0` |
| **Table Headings** | Inter | `11.5px` | 600 | `1.0` | `+0.04em` (Uppercase) |
| **Numerals / Codes** | IBM Plex Mono | `12.5px` | 600 | `1.0` | `0` (Tabular) |

---

## 4. Spacing, Radii & Elevation

### Border Radii
- **Input Fields & Small Buttons:** `var(--radius-sm)` (`8px`)
- **Action Buttons & Inner Pills:** `var(--radius-md)` (`12px`)
- **Dashboard Cards & Containers:** `var(--radius-lg)` (`16px`)
- **Status Badges & Category Pills:** `var(--radius-full)` (`9999px`)

### Shadows
- **Card Default:** `0 1px 3px 0 rgba(16, 24, 40, 0.04), 0 1px 2px -1px rgba(16, 24, 40, 0.02)`
- **Hover Lift:** `0 4px 12px -2px rgba(16, 24, 40, 0.08)`
- **Floating Menus & Dropdowns:** `0 10px 15px -3px rgba(16, 24, 40, 0.08)`

---

## 5. Component Patterns

### 1. Sidebar Navigation
- **Surface:** Pure white `#FFFFFF` with `#EAECF0` right border.
- **Brand Block:** Stylized emblem in dark badge (`#0F172A`) + "ZPPSU-IPO" title + collapse toggle icon.
- **Group Labels:** "Menu" and "Others" in uppercase subtle gray.
- **Nav Links:** 18px Lucide outline icons (`stroke-width: 1.8`).
- **Active State:** Soft gray pill (`#F3F4F6`), dark text `#111827`, font-weight 600.
- **Badges:** Vibrant red pill badge for unread count (`nav-pill-badge`).
- **Footer:** Dark mode switch row with iOS-style slider + quick sign-out link.

### 2. Top Navigation Bar
- **Surface:** Pure white `#FFFFFF`, height `68px`, border bottom `#EAECF0`.
- **Title:** Left-aligned bold heading (`22px`).
- **Action Controls:**
  - Settings quick link (Lucide `settings`).
  - Notification bell with unread red dot (Lucide `bell`).
  - Vertical divider (`#EAECF0`).
  - User Capsule: Circular avatar + full name + email/role + Lucide `chevron-down`.
  - Dropdown: Floating card with user details, profile link, notifications, and logout.

### 3. Filter & Control Row
- **Category Tabs:** Segmented container (`#FFFFFF`, border `#EAECF0`) with pill buttons (`All IP Types`, `Patent`, `Utility Model`, `Industrial Design`, `Trademark`, `Copyright`).
- **Search Pill:** Integrated search input with Lucide `search` icon and status filter select (`All`, `Pending`, `Approved`).
- **Date Selector:** Calendar icon + selected range + granularity dropdown (`Year`, `30 Days`, `7 Days`).
- **Export Trigger:** Outline button with Lucide `download` icon.
- **Primary CTA:** Vibrant blue `#2563EB` button with Lucide `plus` icon.

### 4. Top KPI Metric Cards
- 4-card horizontal responsive grid.
- Metric number in 30px bold tabular font.
- Embedded SVG sparklines with smooth cubic beziers and soft gradient underfills (Emerald Green for submissions/approved, Coral Red for pending review).
- Timeframe stamp: `Data per [Date]`.

### 5. Charts & Visualizations
- **Application Analytics (Line Chart):**
  - Smooth tension (`0.38`), dual series (Blue Submissions `#2563EB`, Red Approvals `#EF4444`).
  - Subtle vertical gradient underfill fading to transparent.
  - Floating white tooltip card with rounded corners and border.
- **Application Status (Semi-Circular Gauge):**
  - Radial SVG arc track with colored segments (Approved Blue, Pending Red, Other Gray).
  - Center metric: Approval Rate `%`, subtext "+16% from last period".
  - Color-coded legend dots and cycle throughput footnote.

### 6. Data Tables & Lists
- Table header: `#F9FAFB` background, uppercase muted font, border bottom.
- Row hover: Subtle `#F8FAFD` tint.
- Monospaced tracking codes: `IPO-YYYY-XXXXXX` in brand blue.
- Status badges: Soft background pill with color-matched border and text.

### 7. Visual Project Cards & View Switcher (Deli-Prop Bottom Widget)
- **Dual Mode View Switcher (`.view-switch-tabs`):** Pill-segmented toggle with interactive buttons (`[ ▦ Cards ]` and `[ ☰ Table ]`).
- **Syncing:** Switching view state maintains live search queries, category filters, and pagination without reloading the page.
- **Card Anatomy (`.project-card`):**
  - High-resolution preview image with category badge overlay (`.project-card-thumb-wrap`).
  - Institutional title with hover color transition.
  - Applicant identifier and timestamp.
  - Status capsule pill with color-matched semantic dot.
  - Interactive details action button with Lucide chevron icon.

### 8. Sub-Header Action Bar (`.sub-header-bar`)
- Located between breadcrumb / page header and the KPI grid on all dashboard interfaces.
- Left block: Descriptive role title and high-clarity scope explanation.
- Right block (`.action-controls-group`): Quick navigation CTAs, filtered queue shortcuts, and export triggers.

### 9. Professional Admin Input & Form Architecture
Governed by UI-UX Kit (§B2 Dashboard/Admin & §B3 App/Product UI standards):
- **Typography & Proportions:**
  - Base input height `42px`, padding `9.5px 13px`, font-size `13.5px`, border-radius `var(--radius-md)` (`12px`).
  - Active focus ring: `3px` glowing aura (`0 0 0 3px rgba(37, 99, 235, 0.14)`) with high-contrast cobalt border (`#2563EB`).
  - Dark mode focus ring: High-visibility electric blue (`#3B82F6` with `rgba(59, 130, 246, 0.22)` glow).
- **Icon Adornment (`.input-icon-wrap`):**
  - Left prefix icon (`.input-icon-left`) optically centered at `top: 50%; transform: translateY(-50%)`, 16px size.
  - Interactive tint: Transitions dynamically to brand blue (`#2563EB`) on container `:focus-within`.
  - Right interactive actions: Password reveal toggle (`.password-toggle-btn`) with Lucide `eye`/`eye-off` and clearable inputs (`.input-clear-btn`).
- **Connected Addon Groups (`.input-group`):**
  - Seamlessly fused prefix/suffix text badges (`.input-group-text`) and action buttons (`.input-group-btn`) eliminating double borders.
  - Used for units (e.g. `MB per file`, `@` username handle).
- **Custom Vector Selects (`.form-select`):**
  - Clean inline SVG chevron icon (`stroke: #6B7280`, `#9CA3AF` in dark mode) replacing unsightly native browser dropdown arrows.
- **Modern Accessible Switches (`.form-switch`):**
  - Reusable toggle with smooth spring animation, 44×24px track, 20px circular thumb (`transform: translateX(20px)`), and full `:focus-visible` ring.
  - Color variants: `.form-switch-warning` (amber), `.form-switch-danger` (coral), `.form-switch-success` (emerald).
- **Interactive File Dropzone (`.file-dropzone`):**
  - Drag-and-drop zone with dashed border, active dragover state, preview pill with file name, human-readable file size (KB/MB), and instant remove action.
- **Micro-Interactions & Assistive Feedback:**
  - **Live Character Counters (`.field-counter`):** Monospaced counter (`X / Y`) transitioning to amber at 85% and red at limit.
  - **Password Strength Meter (`.password-strength-wrap`):** 4-segment colored gauge with real-time heuristic assessment (Weak, Fair, Good, Strong).
  - **Live Match Validator:** Automatic comparison between target and confirm fields with instant green check / red warning indicators.
  - **Anti-Double-Submit UX (`.btn-loading`):** Built-in CSS spinner locking the button and preserving layout geometry.

---

## 6. Dark Mode Architecture (`data-theme="dark"`)

ZPPSU-IPO implements a zero-FOUC, high-contrast dark theme calibrated for prolonged operational sessions:

### Theme Tokens Mapping
| Token Name | Light Mode Value | Dark Mode Value (`[data-theme="dark"]`) |
| :--- | :--- | :--- |
| `--color-bg` | `#F8F9FB` | `#0B0F17` (Deep slate background) |
| `--color-surface` | `#FFFFFF` | `#131B2E` (Navy-slate card elevation) |
| `--color-surface-subtle` | `#F8FAFC` | `#1A243B` (Table headers, inputs) |
| `--color-border` | `#EAECF0` | `#22304E` (High-contrast separation) |
| `--color-border-subtle` | `#F2F4F7` | `#1E2B45` (Inner dividers) |
| `--color-text` | `#111827` | `#F8FAFC` (Crisp readable headings) |
| `--color-text-muted` | `#6B7280` | `#94A3B8` (Legible secondary labels) |
| `--color-text-subtle` | `#9CA3AF` | `#64748B` (Muted hints, borders) |
| `--color-primary-600` | `#2563EB` | `#3B82F6` (High-visibility blue) |
| `--color-primary-50` | `#EFF6FF` | `rgba(59, 130, 246, 0.15)` |

### Persistence & Anti-FOUC Implementation
1. **Script Placement:** An inline synchronous script is injected in `<head>` before any stylesheet or body rendering:
   ```html
   <script>
       (function(){
           var saved = localStorage.getItem('theme');
           if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
               document.documentElement.setAttribute('data-theme', 'dark');
           }
       })();
   </script>
   ```
2. **Toggle Control:** An accessible checkbox switch in the sidebar dispatches instant theme updates to both `document.documentElement` and `localStorage`.
3. **Chart Adaptation:** Dynamically updates Chart.js gridline alphas and tooltip canvas borders when theme transitions occur.

---

## 7. Admin Module Modernization Coverage

All primary interfaces in the `admin/` module comply strictly with the Deli-Prop SaaS standard:
- **`admin/dashboard.php`**: Sub-header bar, 4 KPI cards with sparklines, Spline Chart + SVG Semi-circular Gauge, Dual-mode visual project cards vs. tabular list switcher, synchronized filters.
- **`admin/applications.php`**: Sub-header bar with Export CSV & Clear All triggers, 4 KPI cards with sparklines, status category filter tabs, search pill, and rich applications table.
- **`admin/application_view.php`**: Navigation sub-header with tracking code pill, 2-column dossier grid, invention specifications callout, attached documents table with modal preview, assignment card, status update flow, and audit timeline.
- **`admin/applicants.php`**: Sub-header bar, 4 KPI cards with sparklines, applicant type category tabs, college filter pill, and user profile data table.
- **`admin/applicant_view.php`**: Sub-header bar, 2-column layout with applicant profile dossier and submissions history table.
- **`admin/users.php`**: Sub-header bar with `+ New Account` action, 4 KPI cards with sparklines, role category tabs, search pill, user table with avatars, and all modals cleanly extracted outside table markup.
- **`admin/documents.php`**: Sub-header bar, 4 KPI cards with sparklines, format category tabs, search pill, and document registry table with preview/download actions.
- **`admin/audit_logs.php`**: Sub-header bar with Clear All modal, 3 KPI cards with sparklines, date range search pill, and security log table with monospaced IP addresses.
- **`admin/notifications.php`**: Sub-header bar with Mark All as Read, 3 KPI cards with sparklines, read/unread filter tabs, and interactive notification stream cards.
- **`admin/reports.php`**: Sub-header bar with Print / CSV triggers, 4 KPI cards with sparklines, multi-criteria filter grid, and printable summary report.
- **`admin/announcements.php`**: Sub-header bar, 3 KPI cards with sparklines, announcement cards table, and clean modal dialogs outside the table DOM.
- **`admin/settings.php`**: Sub-header bar, categorized multi-card layout (Office identity, quotas, iOS maintenance switch, allowed file format pill tags).
- **`admin/backup.php`**: Sub-header bar, 3 KPI storage sparklines, dual SQL and ZIP archive action cards, and restoration guidelines.
- **`admin/profile.php`**: User profile card with schema resilience and secure credential update.

---

## 8. Authentication Surface Specification (`login.php` & `register.php`)

Governed by `ui-ux-kit` Anti-AI-Slop and Institutional Gateway Standards:
- **Clean Centered Architecture (Single-Column Focus)**:
  - Eliminates clunky 50/50 marketing splits on sign-in pages. Constituents visiting the login portal arrive with high intent to sign in, not to read marketing brochures.
  - Centered institutional card (`max-width: 440px` for login, `760px` for register) with calm multi-layered elevation depth, not thick decorative borders.
  - Discreet top utility bar with "ZPPSU IPO Homepage" return link and dark/light theme switch.
- **University Heraldry & Identity**:
  - Official university seal emblem (`shield-check`) + `Zamboanga Peninsula Polytechnic State University` uppercase tracker.
  - Direct, confident typography in Plus Jakarta Sans and Inter.
- **Form Controls & Inputs**:
  - Native, high-legibility input fields (42px height, 13.5px font-size) with clean `:focus-visible` ring.
  - Password reveal button (`eye` / `eye-off`) with clean interactive hover state.
  - "Remember this device" checkbox and institutional password assistance trigger.
- **Compliance & Legal Footer**:
  - Official university footer citing compliance with Republic Act No. 8293 (IP Code) and RA 10173 (Data Privacy Act of 2012).
  - Direct technical helpdesk contact (`ipo@zppsu.edu.ph`).

---

## 9. Multi-Role Surface Coverage (`applicant/`, `staff/`, `evaluator/`, `manager/`)

Every dashboard, inner queue, dossier view, notification center, and settings page across all 5 user types now adheres to the unified Deli-Prop SaaS standard:

### 1. Applicant Portal (`applicant/`)
- **`applicant/dashboard.php`**: Sub-header bar with greeting, 4 KPI cards with sparklines (Total Filings, In Processing, Approved, Disqualified), recent filings table with Lucide outline buttons, and university announcements.
- **`applicant/my_applications.php`**: Sub-header bar, 4 KPI cards with sparklines, status category segmented filter pills (`All`, `Pending`, `Under Review`, `Returned`, `Approved`, `Rejected`) with count badges, inline search, and pagination.
- **`applicant/submit_application.php`**: Sub-header bar, 2-column filing layout with technical disclosure inputs, filing pipeline stage indicators, and submission requirements checklist.
- **`applicant/application_view.php`**: Sub-header bar with back button, tracking code pill, and status badge; 2-column dossier with technical summary, document preview/download buttons, file dropzone, revision editor, status audit trail timeline, and printable certificate trigger.
- **`applicant/certificate.php`**: Printable Certificate of Approval with institutional typography, gold foil seal, top toolbar with Lucide icons, and print optimization.
- **`applicant/notifications.php`**: Sub-header bar with Mark All as Read, 3 KPI cards (Total, Unread, Archived), filter tabs (`All`, `Unread`, `Read`), and interactive notification stream cards.
- **`applicant/profile.php`**: Sub-header bar, 2-column layout for personal information and academic affiliation with dynamic college/department linkage and secure password change.

### 2. Staff Portal (`staff/`)
- **`staff/dashboard.php`**: Sub-header bar, 4 KPI cards with sparklines (Awaiting Verification, Assigned to Me, Forwarded, Returned), and verification queue table.
- **`staff/verify.php`**: Sub-header bar, 4 KPI cards with sparklines (Awaiting Screening, Under Technical Review, Returned for Revision, Disqualified), status category segmented tabs (`All`, `Pending`, `Under Review`, `Returned`, `Rejected`), inline search, and clean review actions.
- **`staff/application_view.php`**: Sub-header bar with back button, tracking badge, and status badge; 2-column dossier with technical abstract, attached documents with preview/download, assignment card to certified evaluator, return/reject action card with remarks, and status audit timeline.
- **`staff/applicants.php`**: Sub-header bar with registered count badge, search bar, and applicant directory table with classification pills and submission counts.
- **`staff/documents.php`**: Sub-header bar, 4 KPI cards with sparklines (Total Documents, PDF Disclosures, Word Documents, Visual Diagrams), file format filter tabs (`All`, `PDF`, `DOCX`, `PNG`, `JPG`), inline search, and preview/download table.
- **`staff/notifications.php`**: Sub-header bar, 3 KPI cards, segmented filter tabs (`All`, `Unread`, `Read`), and notification list.
- **`staff/profile.php`**: Sub-header bar, 2-column card layout for personal details, recovery email, and security password update.

### 3. Evaluator Portal (`evaluator/`)
- **`evaluator/dashboard.php`**: Sub-header bar, 4 KPI cards with sparklines (Awaiting Evaluation, Recommended Approval, Rejected, Returned), and evaluation queue table.
- **`evaluator/evaluations.php`**: Sub-header bar, 4 KPI cards with sparklines (Awaiting Evaluation, Recommended for Approval, Returned for Revision, Total Assigned Dossiers), segmented status tabs, inline search, and data table.
- **`evaluator/evaluation_view.php`**: Sub-header bar with back button and tracking badge; 2-column layout with technical disclosure, attachments table with modal preview, formal substantive evaluation form card (Recommend Approval / Return / Reject), and recorded technical findings timeline.
- **`evaluator/reports.php`**: Sub-header bar with analytics triggers, 4 KPI cards with sparklines, period filter bar, substantive approval distribution chart, and evaluation log table.
- **`evaluator/notifications.php`**: Sub-header bar, 3 KPI cards, segmented filter tabs (`All`, `Unread`, `Read`), and assignment notifications stream.
- **`evaluator/profile.php`**: Sub-header bar with Certified Examiner badge, 2-column card layout for contact details and password update.

### 4. Manager Portal (`manager/`)
- **`manager/dashboard.php`**: Sub-header bar, 4 KPI cards with sparklines (Awaiting Final Approval, Approved Filings, Rejected Applications, Total IP Filings), and executive queue table.
- **`manager/reports.php`**: Sub-header bar with Export CSV and Print PDF actions, 4 KPI cards with sparklines (Total Filings, In Screening/Review, Awaiting Executive Sign-Off, Approved/Registered), multi-criteria filter form, monthly velocity bar chart, and matching applications table with view links.
- **`manager/application_view.php`**: Sub-header bar with back button and tracking badge; 2-column dossier with technical abstract, Examiner Findings & Endorsement quote card, attachments table, Executive Decision & Sign-Off card (`Final Approve` / `Final Reject`), and governance audit trail.
- **`manager/notifications.php`**: Sub-header bar, 3 KPI cards, segmented filter tabs (`All`, `Unread`, `Read`), and decision requests notification stream.
- **`manager/profile.php`**: Sub-header bar with Executive Authority badge, 2-column card layout for credentials and password update.


