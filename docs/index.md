---
layout: home

hero:
  name: Symfony Carve
  text: Carve for Symfony
  tagline: Twig filters, services, console commands, validation, and caching for the Carve markup language
  image:
    src: /logo.svg
    alt: Symfony Carve
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: Configuration
      link: /guide/configuration
    - theme: alt
      text: View on GitHub
      link: https://github.com/markup-carve/symfony-carve

features:
  - title: Twig
    details: The carve filter and function, plus carve_document, carve_file, carve_format, and carve_lint
  - title: Services
    details: CarveRenderer and CarveTools render, format, lint, and import Carve in controllers and services
  - title: Console Commands
    details: carve:render, carve:fmt, carve:lint, and carve:convert
  - title: Validation
    details: The ValidCarve constraint checks markup with Carve parse, render, and lint diagnostics
  - title: Caching
    details: Optional render caching through any Symfony cache pool
  - title: Extensions and Diagrams
    details: Register Carve extensions, named 0.2 bundles, and diagram fence presets in the bundle configuration
---
