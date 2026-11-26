# Caching

```yaml
carve:
    cache:
        enabled: true
        pool: cache.app    # any service implementing Symfony\Contracts\Cache\CacheInterface
        ttl: 3600          # seconds; null keeps the pool default
        prefix: carve
        version: null
```

Entries are keyed by the source and a fingerprint of every render option, the extension
configuration and the carve-php version, so changing the configuration or upgrading the
engine never serves stale HTML. File renders below `include_root` also record a content
hash for each include target, including missing ones, and re-render when any of them
changes or appears.

An extension passed as an object that holds a closure cannot be fingerprinted, so caching
stays off for that renderer unless you set `cache.version` and bump it whenever that
extension changes. Extensions configured by name, including `wikilinks` with
`url_template`, are fingerprinted normally.
