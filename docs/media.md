# Media management

## Storage

Uploaded files are stored through `App\Service\Media\MediaStorageInterface`. The default implementation, `FlysystemMediaStorage`, writes to the `media.storage` Flysystem local adapter at `public/uploads/media` and exposes URLs under `/uploads/media`.

To move to S3-compatible storage later:

1. Install `league/flysystem-aws-s3-v3`.
2. Add an S3 adapter under `flysystem.storages.media.storage` (and `media.cache.storage`) in `config/packages/flysystem.yaml`.
3. Implement or configure a storage service with the correct public base URL and alias `App\Service\Media\MediaStorageInterface` to it.
4. Update `app.media.public_base_url` if the URL does not map to `/uploads/media`.

No application code depends on local paths; only the adapter/configuration changes.

## Upload validation

`MediaUploader` enforces, in order:

- The upload completed successfully.
- Size does not exceed `media.max_upload_bytes` (default 10 MB, settings-backed).
- No filename segment is a blocked executable/script extension (`php`, `phtml`, `phar`, `cgi`, `js`, `html`, `svg`, `xml`, `exe`, …). This rejects double extensions such as `shell.php.png`.
- Detected MIME type via `finfo` is one of JPEG, PNG, WebP, GIF, PDF, TXT, CSV or DOCX.
- The client extension matches the detected MIME type.

SVG is intentionally rejected until a separate sanitization pipeline exists. Files receive a random 32-hex filename plus a canonical extension and are stored under a `YYYY/MM` prefix.

Images are re-encoded with GD on upload, which strips EXIF/metadata and validates the image bytes. Width and height are recorded in the database.

## Variants

LiipImagine generates derivatives on demand from the Flysystem loader:

| Variant | Size | Notes |
|---|---|---|
| `thumbnail` | max 320×320, outbound | library grid |
| `medium` | max 800×800 | detail preview |
| `large` | max 1600×1600 | article use |
| `webp` | max 1200×1200, WebP | modern format |

Variant URLs point to `/media/cache/resolve/{filter}/{storageKey}`; the resolver writes to `public/uploads/media/cache` and redirects to the static file. The URLs are recorded in `media.variants` at upload time.

## Library and picker

- `GET /admin/media` lists, searches (name/alt/caption), filters by image/document and date, and paginates at 24 per page.
- `POST /admin/media` uploads a file (CSRF-protected Symfony form).
- `GET|POST /admin/media/{id}/edit` edits alt text and caption.
- `POST /admin/media/{id}/delete` deletes the original and soft-deletes the record. Deletion is refused while the file is referenced by an article featured image, category image, advertisement or profile avatar.
- `GET /admin/media/picker?q=` returns JSON metadata and variant URLs for the editor/featured-image picker.

Permissions: `media.upload` for the library, `media.manage.any` for all files; otherwise users may manage only their own uploads.

## Web-server hardening

The upload directory must never execute PHP. For Nginx:

```nginx
location ^~ /uploads/ {
    try_files $uri =404;
    location ~ \.(php|phtml|phar)$ { return 403; }
}
```

For Apache, disable script execution in `public/uploads` with `php_flag engine off` (or an equivalent `<FilesMatch>` deny). Serve uploads with long cache headers and only the allowed MIME types.

## Limits and retention

The size limit is read from the settings service. Deleting a media record removes the original file; LiipImagine cache files are disposable and may be pruned independently (`bin/console liip:imagine:cache:remove`).
