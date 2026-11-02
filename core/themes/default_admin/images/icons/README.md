# Default Admin icons

Monochrome UI icons owned by the Default Admin theme. Not a shared or
contrib icon library.

Rules:

- One flat directory. Lowercase kebab-case file names that describe what the
  icon shows (`chevron-down.svg`, `pager-first.svg`). No color, size or hex
  values in names, and no color sub-folders.
- Paint with `currentColor` only; every file has a `viewBox`.
- Register an icon in `css/base/icons.pcss.css` as `--admin-icon-*` only when
  theme CSS uses it. Several stable property names may alias one file (for
  example `--admin-icon-add` uses `plus.svg`).
- Use icons as `mask-image` and tint them with `background-color`
  (`currentColor` or an `--admin-color-*` token). In forced-colors mode use a
  system color such as `CanvasText` or `LinkText`.
- Do not add icons for contributed modules; they ship their own.
- Multicolor assets live elsewhere: MIME file icons in `images/file-types/`,
  brand marks in `images/logos/`.
