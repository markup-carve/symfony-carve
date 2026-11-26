# Validation

With `symfony/validator` installed, the `ValidCarve` constraint checks that a value
parses as Carve:

```php
use MarkupCarve\SymfonyCarve\Validator\ValidCarve;

final class Comment
{
    #[ValidCarve(preset: 'comment', maxLength: 5000, lint: true)]
    public string $body = '';
}
```

| Option | Default | Description |
|--------|---------|-------------|
| `preset` | `null` | The markup has to fit this profile: `full`, `article`, `comment` or `minimal`. |
| `strict` | `false` | Parse warnings count as violations. |
| `lint` | `false` | Lint findings count as violations. |
| `maxLength` | `null` | Maximum length in characters. |
| `message` | see below | Violation message; `{{ error }}` is replaced with the reason. |

The default message is `This value is not valid Carve markup: {{ error }}`. Violation
codes are `ValidCarve::INVALID_ERROR`, `TOO_LONG_ERROR` and `DISALLOWED_ERROR`. `null`
and the empty string pass, so combine it with `NotBlank` when a value is required.
