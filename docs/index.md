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
    details: CarveRenderer and CarveTools are autowirable for controllers and services
  - title: Console Commands
    details: carve:render, carve:lint, carve:fmt, carve:convert, and carve:trace
  - title: Validation
    details: The ValidCarve constraint checks markup against a profile, lint rules, and a length limit
  - title: Caching
    details: Optional render caching through any Symfony cache pool
  - title: Extensions and Diagrams
    details: Enable carve-php extensions and diagram fence presets by name in the bundle configuration
---
