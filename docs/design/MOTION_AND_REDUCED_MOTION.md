# Motion and Reduced Motion — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Derived from:** Master Source §18.2 rule 8 ("Avoid heavy animation. Motion is functional… never
decorative. Low-end Android devices are the baseline, not the exception.")
**Scope:** DOCUMENTATION ONLY. No animation exists.

---

## 1. Motion position

**Motion in this product is functional or it is absent.** There is no third category.

Every animation must answer one of four questions:

1. **Where did this come from?** (a sheet rising from the bottom edge)
2. **Where did this go?** (a dismissed item sliding out)
3. **What changed?** (a status badge updating; a value incrementing)
4. **Is something happening?** (a sync spinner; a progress bar)

If an animation answers none of these, it is removed. "It feels nicer" is not an answer.

### Why the position is this strict

The baseline device is a low-end Android phone. A dropped frame in an animation on a flagship is
invisible; on the baseline device it is a visible stutter that makes the product feel broken. The
cheapest way to guarantee smooth motion on a slow device is to have less of it.

The tracking portal has the strictest budget of all, because it is opened over a poor connection by
someone who wants one fact.

---

## 2. Duration tokens

| Token | Value | Use |
|---|---|---|
| `duration.instant` | 0 ms | State changes that must feel immediate: checkbox tick, radio select, focus ring |
| `duration.fast` | 120 ms | Small-element transitions: hover, press, chip select, tooltip |
| `duration.normal` | 200 ms | **Default.** Component enter/exit: menu, snackbar, banner, expander |
| `duration.slow` | 280 ms | Large surface transitions: bottom sheet, dialog, drawer, page |
| `duration.deliberate` | 400 ms | Reserved. Only where a user must notice a consequential change — a variance figure updating on shift close |

**Ceiling: 400 ms.** No animation in this product exceeds `duration.deliberate`. An animation longer
than 400 ms is a delay the user is forced to watch.

**Floor:** anything under 100 ms reads as instantaneous, so `duration.fast` is the shortest non-zero
value worth specifying.

### Duration by distance

Larger surfaces travel further and need slightly longer, but the relationship is sub-linear:

| Element size | Duration |
|---|---|
| Small (chip, checkbox, icon) | `duration.fast` |
| Medium (menu, card, banner) | `duration.normal` |
| Large (sheet, dialog, page) | `duration.slow` |

---

## 3. Easing tokens

| Token | Curve | Use |
|---|---|---|
| `easing.standard` | `cubic-bezier(0.2, 0, 0, 1)` | **Default.** Elements moving within the screen; most transitions |
| `easing.decelerate` | `cubic-bezier(0, 0, 0, 1)` | Elements **entering** the screen — fast in, settling gently |
| `easing.accelerate` | `cubic-bezier(0.3, 0, 1, 1)` | Elements **leaving** the screen — gentle start, fast exit |
| `easing.linear` | `cubic-bezier(0, 0, 1, 1)` | Only for continuous indeterminate motion: a spinner rotation, a progress shimmer |

### Rules

1. **Nothing eases linearly except continuous indeterminate motion.** Linear movement reads as
   mechanical.
2. **No spring, bounce, overshoot, or elastic easing anywhere.** These read as playful, which conflicts
   with the professional attribute, and they cost extra frames on the baseline device.
3. **Enter and exit are asymmetric.** Entering uses `easing.decelerate`; leaving uses
   `easing.accelerate`. An element that leaves slowly wastes the user's time.

---

## 4. Permitted motion, by purpose

### 4.1 Orientation — where things come from and go

| Transition | Duration | Easing | Specification |
|---|---|---|---|
| Bottom sheet enter | `duration.slow` | `easing.decelerate` | Translate from bottom edge; scrim fades in over `duration.normal` |
| Bottom sheet exit | `duration.normal` | `easing.accelerate` | Translate to bottom edge; scrim fades out |
| Dialog enter | `duration.normal` | `easing.decelerate` | Fade in with a 2% scale-up from 0.98; scrim fades in |
| Dialog exit | `duration.fast` | `easing.accelerate` | Fade out, no scale |
| Drawer enter/exit | `duration.slow` / `duration.normal` | decelerate / accelerate | Translate from the leading edge |
| Menu / dropdown open | `duration.fast` | `easing.decelerate` | Fade with a 4 dp translate from its anchor |
| Page transition (Android) | `duration.slow` | `easing.standard` | Platform default forward/back; never a custom flourish |
| Page transition (Web) | `duration.instant` | — | No page transition on Console Web or the portal |

### 4.2 State change — what changed

| Transition | Duration | Easing |
|---|---|---|
| Button press feedback | `duration.fast` | `easing.standard` |
| Hover (pointer only) | `duration.fast` | `easing.standard` |
| Focus ring appearance | `duration.instant` | — |
| Checkbox / radio / switch | `duration.fast` | `easing.standard` |
| Status badge change | `duration.normal` | `easing.standard` — cross-fade only, no movement |
| Expander open/close | `duration.normal` | `easing.standard` — height, plus content fade |
| Banner enter | `duration.normal` | `easing.decelerate` |
| Snackbar enter/exit | `duration.normal` / `duration.fast` | decelerate / accelerate |
| List item removal | `duration.normal` | `easing.accelerate` — fade and collapse height |
| Shift-close variance update | `duration.deliberate` | `easing.standard` — the one place slowness is the point |

### 4.3 Progress — is something happening

| Indicator | Specification |
|---|---|
| Indeterminate spinner | Continuous rotation, 1000 ms per revolution, `easing.linear` |
| Determinate progress bar | Width transitions at `duration.normal`, `easing.standard` |
| Skeleton shimmer | 1400 ms cycle, `easing.linear`, opacity range 0.5–1.0 on `color.neutral.100`. No moving gradient sweep |
| Sync indicator | Rotation while `SYNC_IN_PROGRESS`; **static in every other sync state** |
| Pull-to-refresh | Platform default on Android; absent on Web |

**A spinner appears only after 400 ms of waiting.** An operation that resolves in 150 ms should show
nothing; a spinner that flashes on and off is worse than no spinner.

**A spinner never blocks a whole screen** in the Ops app when the operation can be queued. Offline-first
means the user continues and the work syncs (Rule 07).

---

## 5. Reduced motion contract

The system honours the platform's reduced-motion preference: `prefers-reduced-motion: reduce` on Web,
and the "Remove animations" / disable-animations accessibility setting on Android.

### The contract, precisely

**Reduced motion does not mean "no feedback". It means "no movement".** Removing the feedback entirely
would leave a user unable to tell that anything happened — a worse outcome.

| Normal | Reduced motion |
|---|---|
| Bottom sheet slides up | Sheet cross-fades in over `duration.fast` |
| Dialog fades and scales | Dialog appears with an opacity-only fade over `duration.fast` |
| Drawer slides in | Drawer cross-fades in |
| Menu translates and fades | Menu fades only |
| Page transition | No transition; instant |
| List item collapses on removal | Item disappears; the list reflows instantly |
| Expander animates height | Content appears instantly |
| Status badge cross-fades | Badge changes instantly |
| Spinner rotates | **Rotation stops.** A static indicator plus the text "Memuat…" replaces it |
| Skeleton shimmers | **Shimmer stops.** Static `color.neutral.100` blocks remain |
| Sync indicator rotates | **Rotation stops.** Static icon plus the text "Sedang Disinkronkan" |
| Progress bar animates width | Width updates in discrete steps, no tween |

### Binding rules

1. **Reduced motion never removes information.** Every state that motion communicated must still be
   communicated — by text, icon, or an instant visual change.
2. **Reduced motion never removes the focus indicator.** The focus ring is `duration.instant`
   regardless (`SHAPE_BORDER_ELEVATION.md` §4).
3. **Reduced motion is checked at animation time, not at app start.** A user who changes the setting
   mid-session gets the new behaviour without restarting.
4. **Reduced motion applies to all four surfaces**, including the tracking portal.
5. **Duration under reduced motion is `duration.fast` or `duration.instant`.** Nothing longer.
6. **An animation that cannot be reduced must not exist.** If a design depends on movement to convey
   something, the design is wrong; add text.

---

## 6. Prohibited motion

| Prohibited | Reason |
|---|---|
| Any decorative animation | Master Source §18.2 rule 8 |
| Parallax | Decorative; costs frames; causes discomfort |
| Auto-playing video or animated background | Decorative; data cost; distraction |
| Carousels that advance on their own | Removes user control; a WCAG 2.2 SC 2.2.2 problem |
| Spring, bounce, elastic, or overshoot easing | §3 rule 2 |
| Any animation above 400 ms | §2 ceiling |
| Any flash or blink above 3 Hz | Seizure risk (WCAG 2.2 SC 2.3.1) |
| Animated illustrations in empty or error states | Decorative; delays comprehension |
| Motion as the sole indicator of a state change | Fails reduced motion and fails accessibility |
| A shimmer or pulse on a money value | Implies uncertainty about a figure that must be definite |
| Animating a status transition along a path | Implies process detail the system does not have |
| A loading animation that hides a queued offline operation | Rule 07: queued work is visible, not hidden |
| Animation on the tracking portal beyond a fade and a spinner | Performance baseline (§1) |
| Confetti, celebration, or reward animation | Conflicts with the professional attribute; trivialises operational events |
| Scroll-triggered reveal animation | Decorative; delays content on the baseline device |
| Animating a scrim above `duration.normal` | Delays access to a modal's content |

---

## 7. Performance budget

| Constraint | Value |
|---|---|
| Target frame rate | 60 fps on the baseline device |
| Maximum simultaneous animations in one view | 2 |
| Maximum animated elements in a list | 1 (the item being added or removed) |
| Properties permitted to animate | opacity, transform (translate, scale) |
| Properties prohibited from animating | width, height, top, left, margin, padding, box-shadow, colour of a large surface |
| Tracking portal animation budget | Fade and spinner only |

Animating layout properties forces reflow on every frame, which is precisely the cost the baseline
device cannot absorb. Height animation on an expander is the one permitted exception, and only for a
container of bounded size.

**No frame-rate measurement has been performed, because no runtime exists.** This budget is an
obligation on the Steps that build each surface, not a verified result.
