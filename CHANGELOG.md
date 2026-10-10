# Changelog

## 5.0.2

- Keep each cleanup batch in a separate backup directory instead of deleting the previous backup.
- Keep backups outside `plugin_data` and skip symbolic links.
- Validate configuration values before scheduling cleanup.
- Add backup retention tests, PHPStan analysis and a pinned DevTools build.
