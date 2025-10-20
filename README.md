# Barber Turns

Barber Turns is a lightweight PHP 8.3 + MySQL app that keeps a barber shop's walk-in queue fair and transparent. Staff can monitor rotation status in real time while customers see a TV-friendly view that refreshes automatically.

- **Core flow:** Barbers progress through `available → busy_walkin → busy_appointment → available`, keeping the queue balanced.
- **Stack:** PHP 8.3 (DreamHost VPS), MySQL 8, minimalist front-end assets.
- **Docs:** Full product requirements live in [`prd.md`](prd.md).
