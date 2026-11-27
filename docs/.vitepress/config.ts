import { defineConfig } from 'vitepress'
import { carveMarkdown } from 'carve-grammars/shiki'

// The versioned build (scripts/build-versioned-docs.sh on main) sets these per side.
const docsBase = process.env.DOCS_BASE ?? '/symfony-carve/'
const docsVersionText = process.env.DOCS_VERSION_TEXT
const docsLatestBase = process.env.DOCS_LATEST_BASE ?? '/symfony-carve/'
const docsOtherBase = process.env.DOCS_OTHER_BASE ?? '/symfony-carve/0.2/'
const siteUrl = (base: string) => `https://markup-carve.github.io${base}`

export default defineConfig({
  title: 'Symfony Carve',
  description: 'Carve markup language integration for Symfony: Twig filters, services, console commands, validation, and caching',

  base: docsBase,

  cleanUrls: true,

  srcExclude: ['README.md'],

  head: [
    ['link', { rel: 'icon', href: `${docsBase}favicon.svg`, type: 'image/svg+xml' }],
    // Marks a versioned build, so the theme styles only the version menu as a switch.
    ...(docsVersionText ? [['meta', { name: 'docs-version', content: docsVersionText }] as [string, Record<string, string>]] : []),
    ...(process.env.VITE_DOCS_SERIES === 'dev' ? [['meta', { name: 'robots', content: 'noindex' }] as [string, Record<string, string>]] : []),
  ],

  markdown: {
    ...carveMarkdown(),
    // Twig samples such as `{{ error }}` in inline code must not be read as Vue interpolation.
    config(md) {
      const codeInline = md.renderer.rules.code_inline!
      md.renderer.rules.code_inline = (tokens, idx, options, env, self) =>
        codeInline(tokens, idx, options, env, self).replace(/^<code/, '<code v-pre')
    },
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      {
        text: 'Links',
        items: [
          { text: 'Playground', link: 'https://sandbox.dereuromark.de/sandbox/carve' },
          { text: 'Demo App', link: 'https://github.com/markup-carve/symfony-carve-demo' },
          { text: 'carve-php', link: 'https://github.com/markup-carve/carve-php' },
          { text: 'Laravel Package', link: 'https://github.com/markup-carve/laravel-carve' },
          { text: 'Carve Docs', link: 'https://markup-carve.github.io/carve/' },
          { text: 'Changelog', link: 'https://github.com/markup-carve/symfony-carve/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/markup-carve/symfony-carve' },
          { text: 'Issues', link: 'https://github.com/markup-carve/symfony-carve/issues' },
        ],
      },
      ...(docsVersionText ? [{
        text: docsVersionText,
        items: [
          { text: process.env.DOCS_LATEST_LABEL ?? '0.1 (latest)', link: siteUrl(docsLatestBase), target: '_self' },
          { text: process.env.DOCS_OTHER_LABEL ?? '0.2 (dev)', link: siteUrl(docsOtherBase), target: '_self' },
        ],
      }] : []),
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
          ],
        },
        {
          text: 'Usage',
          items: [
            { text: 'Services and Twig', link: '/guide/services-and-twig' },
            { text: 'Tools, Validation, and Commands', link: '/guide/tools-and-commands' },
          ],
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Includes and File Rendering', link: '/guide/includes' },
            { text: 'Extensions', link: '/guide/extensions' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/markup-carve/symfony-carve' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: `https://github.com/markup-carve/symfony-carve/edit/${process.env.DOCS_EDIT_BRANCH ?? 'main'}/docs/:path`,
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright Markup Carve',
    },
  },
})
