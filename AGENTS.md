## Version Bumping

When bumping the version number, update ALL 3 places:
1. `simple-hotel-crm.php` — the `Version:` header
2. `update.json` — the `"version"` field
3. `update.json` — the changelog section

After every version bump, commit and push to GitHub so the live site's updater can pull the new version.
