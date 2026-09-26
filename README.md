# VelaFront — Headless Magento 2 Storefront (Next.js · React · TypeScript)

**VelaFront is a modern headless storefront for Magento 2 (Adobe Commerce)** — a fast, SEO-first frontend built on **Next.js 15**, **React 19**, **TypeScript**, **Tailwind CSS**, and **shadcn/ui**, talking to Magento entirely over **GraphQL**. It's a production-grade alternative to Luma, PWA Studio, and Hyvä for merchants who want a decoupled React frontend without giving up Magento's catalog, checkout, and Page Builder content.

- 🌐 **Website:** [velafront.com](https://velafront.com)
- 🏢 **By:** [Byte8 Ltd](https://byte8.io)

> This repository is **`byte8/module-velafront`** — the Magento 2 **companion module** that powers VelaFront's CMS layer. It exposes Magento **Page Builder** content as clean, structured JSON via GraphQL so the headless frontend can render native React components instead of dumping raw HTML. See [The companion module](#the-companion-module) below. The storefront application itself lives in the VelaFront theme monorepo.

---

## Why headless Magento 2 with VelaFront

Traditional Magento themes (Luma) couple the frontend to PHP/Knockout and struggle with Core Web Vitals. VelaFront decouples the storefront so you get:

- **A React/Next.js frontend** — Server Components, streaming SSR, ISR, and static generation for instant, cacheable pages.
- **Real Core Web Vitals** — engineered for 90+ Lighthouse scores: LCP < 2.5s, INP < 200ms, CLS < 0.1 (see the SEO strategy docs).
- **Magento as a headless commerce backend** — your catalog, pricing, inventory (MSI), customers, and checkout stay in Magento; VelaFront consumes them over GraphQL.
- **No forking to customize** — every feature is a self-contained workspace package you can compose, override, or swap.

Ideal for merchants and agencies evaluating a **PWA Studio alternative**, a **headless Hyvä alternative**, or any **React storefront for Magento 2 / Adobe Commerce**.

## Features

### Frontend & architecture
- **Next.js 15 (App Router)** with React Server Components, SSR, ISR, and SSG
- **TypeScript** in strict mode end to end
- **Tailwind CSS 4** + **shadcn/ui** — accessible, de-brandable, zero-runtime component library
- **Turborepo + Bun** monorepo of composable feature packages (catalog, cart, checkout, account, search, CMS, UI, …)
- **graphql-request** + Next.js fetch cache — a thin, fast data layer with no duplicate client cache

### Commerce
- Product listing & detail pages for all Magento product types (simple, configurable, bundle, grouped, virtual, downloadable)
- Cart, mini-cart, and a modular multi-step checkout
- Customer accounts and authentication (Better Auth)
- **Live inventory** (MSI-aware) and **VAT validation** for B2B
- **Payment integrations**: Stripe, PayPal, Adyen, Braintree, Klarna, Mollie
- **Search integrations**: Algolia, Meilisearch, plus an AI-powered search package
- Personalization and white-label packaging

### CMS & Page Builder (this module)
- Renders Magento **Page Builder** pages and blocks as **native React components**, not raw HTML
- Structured content tree over GraphQL — rows, columns, text, headings, images, buttons, sliders, tabs, product lists, widgets
- Magento directives (`{{media}}`, `{{store}}`, widgets) resolved server-side into ready-to-use URLs and data

### SEO & performance
- Server-rendered metadata, canonical URLs, and Open Graph / social sharing tags
- Structured data (schema.org) for rich search results
- `next/font` with size-adjust, explicit image dimensions, and skeletons that match final layout — no cumulative layout shift
- ISR and edge caching for fast, crawlable pages

## The companion module

`Byte8_VelaFront` is the server-side half of VelaFront. Magento's native GraphQL returns Page Builder content as a single `content` HTML string, which is awkward to render safely and semantically in React. This module parses that HTML into a **structured node tree** and exposes it through two GraphQL queries.

### Requirements
- Magento 2 (Open Source / Adobe Commerce) with GraphQL enabled
- PHP 8.1 – 8.5

### Installation

```bash
composer require byte8/module-velafront
bin/magento module:enable Byte8_VelaFront
bin/magento setup:upgrade
bin/magento setup:di:compile
```

### GraphQL API

```graphql
# A CMS page with structured Page Builder content
query {
  velaFrontCmsPage(identifier: "home") {
    title
    meta_title
    meta_description
    content_html          # raw Page Builder HTML (fallback)
    content {             # structured tree for React rendering
      version
      nodes {
        content_type      # row, column, text, image, buttons, ...
        appearance
        data { html heading_type image_url image_alt link_url }
        children { content_type data { html } }
      }
    }
  }
}

# A CMS block by identifier
query {
  velaFrontCmsBlock(identifier: "footer_links") {
    title
    content { nodes { content_type data { html } } }
  }
}
```

Both queries are cached via Magento's GraphQL resolver cache, tagged by CMS page/block identity so content edits invalidate correctly.

## Documentation

Full architecture, SEO, CMS rendering, URL routing, and configuration guides ship with the VelaFront theme (`docs/`), covering the technical architecture, Core Web Vitals strategy, and Page Builder rendering model. Learn more at [velafront.com](https://velafront.com).

## Support

Byte8 Ltd — support@byte8.io

## License

Proprietary — © [Byte8 Ltd](https://byte8.io). Commercial licensing for VelaFront is available at [velafront.com](https://velafront.com). See `LICENSE` for details.
