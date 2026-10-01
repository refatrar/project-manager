#!/usr/bin/env python3
"""WCAG 2.x contrast check for every token pair in docs/design-system/README.md section 2.

Run: python3 docs/design-system/contrast-check.py   (exits 1 if any pair fails)
Update the values here whenever a colour token changes."""
import sys
def hx(c):
    c = c.lstrip('#'); return tuple(int(c[i:i+2], 16) / 255 for i in (0, 2, 4))
def lin(v): return v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4
def L(c): r, g, b = map(lin, hx(c)); return 0.2126*r + 0.7152*g + 0.0722*b
def ratio(a, b):
    la, lb = sorted((L(a), L(b)), reverse=True); return (la + 0.05) / (lb + 0.05)
def mix(fg, bg, alpha):
    f, b = hx(fg), hx(bg)
    return '#' + ''.join(f'{round((alpha*x + (1-alpha)*y)*255):02X}' for x, y in zip(f, b))

def run(title, pairs):
    print(f"\n## {title}")
    worst = []
    for label, fg, bg, need in pairs:
        r = ratio(fg, bg); ok = r >= need
        print(f"{'PASS' if ok else 'FAIL'} {r:5.2f}:1 (need {need}) {label:52} {fg} on {bg}")
        if not ok: worst.append(label)
    return worst

W, CANVAS, SEC = '#FFFFFF', '#F1F5F4', '#E3F0EC'
light = [
 ('foreground on card', '#1A1D23', W, 4.5), ('foreground on canvas', '#1A1D23', CANVAS, 4.5),
 ('foreground on secondary/accent', '#1A1D23', SEC, 4.5),
 ('muted-foreground on card', '#4A5160', W, 4.5), ('muted-foreground on canvas', '#4A5160', CANVAS, 4.5),
 ('muted-foreground on secondary', '#4A5160', SEC, 4.5),
 ('subtle-foreground on card', '#5C6370', W, 4.5), ('subtle-foreground on canvas', '#5C6370', CANVAS, 4.5),
 ('subtle-foreground on secondary', '#5C6370', SEC, 4.5),
 ('primary-foreground on primary', '#FFFFFF', '#0F766E', 4.5), ('primary-foreground on primary-hover', '#FFFFFF', '#115E59', 4.5),
 ('primary as link text on card', '#0F766E', W, 4.5), ('primary as link text on canvas', '#0F766E', CANVAS, 4.5),
 ('input border vs card (UI 3:1)', '#6B7280', W, 3), ('input border vs canvas (UI 3:1)', '#6B7280', CANVAS, 3),
 ('focus ring vs card (UI 3:1)', '#0F766E', W, 3),
 ('success-foreground on success', '#FFFFFF', '#15803D', 4.5), ('success as text on card', '#15803D', W, 4.5),
 ('success-subtle-foreground on success-subtle', '#166534', '#F0FDF4', 4.5),
 ('warning-foreground on warning', '#FFFFFF', '#B45309', 4.5), ('warning as text on card', '#B45309', W, 4.5),
 ('warning-subtle-foreground on warning-subtle', '#92400E', '#FFFBEB', 4.5),
 ('destructive-foreground on destructive', '#FFFFFF', '#B91C1C', 4.5), ('destructive as text on card', '#B91C1C', W, 4.5),
 ('destructive-subtle-foreground on destructive-subtle', '#991B1B', '#FEF2F2', 4.5),
 ('info-foreground on info', '#FFFFFF', '#1D4ED8', 4.5), ('info as text on card', '#1D4ED8', W, 4.5),
 ('info-subtle-foreground on info-subtle', '#1E40AF', '#EFF6FF', 4.5),
 ('sidebar foreground on sidebar', '#1A1D23', '#E7F0ED', 4.5), ('sidebar muted text on sidebar', '#4A5160', '#E7F0ED', 4.5),
 ('sidebar subtle text on sidebar', '#5C6370', '#E7F0ED', 4.5), ('active nav item text on sidebar-accent', '#115E59', '#D4E9E3', 4.5),
 ('muted text on sidebar-accent', '#4A5160', '#D4E9E3', 4.5), ('sidebar-primary-foreground on sidebar-primary', '#FFFFFF', '#0F766E', 4.5),
 ('sidebar ring vs sidebar (UI 3:1)', '#0F766E', '#E7F0ED', 3),
]
CARD, BG, POP = '#1A1D22', '#121417', '#242830'
dark = [
 ('subtle-foreground on card', '#A3A9B3', CARD, 4.5), ('subtle-foreground on background', '#A3A9B3', BG, 4.5), ('subtle-foreground on popover', '#A3A9B3', POP, 4.5),
 ('primary-foreground on primary-hover', '#042F2E', '#5EEAD4', 4.5), ('sidebar-primary-foreground on sidebar-primary', '#042F2E', '#2DD4BF', 4.5),
 ('foreground on card', '#F4F5F7', CARD, 4.5), ('foreground on background', '#F4F5F7', BG, 4.5), ('foreground on popover', '#F4F5F7', POP, 4.5),
 ('muted-foreground on card', '#B4B9C2', CARD, 4.5), ('muted-foreground on background', '#B4B9C2', BG, 4.5), ('muted-foreground on popover', '#B4B9C2', POP, 4.5),
 ('primary-foreground on primary', '#042F2E', '#2DD4BF', 4.5), ('primary as link text on card', '#2DD4BF', CARD, 4.5), ('primary as link text on popover', '#2DD4BF', POP, 4.5),
 ('input border vs card (UI 3:1)', '#8B939F', CARD, 3), ('input border vs popover (UI 3:1)', '#8B939F', POP, 3),
 ('focus ring vs card (UI 3:1)', '#2DD4BF', CARD, 3),
 ('success-foreground on success', '#052E16', '#4ADE80', 4.5), ('success as text on card', '#4ADE80', CARD, 4.5),
 ('warning-foreground on warning', '#451A03', '#FBBF24', 4.5), ('warning as text on card', '#FBBF24', CARD, 4.5),
 ('destructive-foreground on destructive', '#450A0A', '#F87171', 4.5), ('destructive as text on card', '#F87171', CARD, 4.5),
 ('info-foreground on info', '#172554', '#60A5FA', 4.5), ('info as text on card', '#60A5FA', CARD, 4.5),
]
for name, c in [('success', '#4ADE80'), ('warning', '#FBBF24'), ('destructive', '#F87171'), ('info', '#60A5FA')]:
    tint = mix(c, CARD, 0.15)
    dark.append((f'{name} text on {name}-subtle (15% tint over card = {tint})', c, tint, 4.5))
fails = run('Light', light) + run('Dark', dark)
print('\nFAILURES:', fails)
sys.exit(1 if fails else 0)
