# Laravel Shared

Deploy Laravel apps to shared hosting with one Artisan command.

`m7md-5ttab/laravel-shared` is a deployment toolkit for Laravel 11+ applications running on traditional shared hosting such as cPanel, Hostinger, Namecheap, GoDaddy, DirectAdmin, and similar environments.

It helps you publish a Laravel app beside `public_html` without manually copying files, patching `index.php`, or guessing whether symlinks will work on the server.

## Why Use It

- One command for checking, preparing, and deploying
- Safe `--dry-run` mode before any real changes
- Automatic handling for `public_html`, `public/storage`, and writable Laravel directories
- Supports both symlink and copy deployments
- Keeps deployment manifests and rollback data
- Optional Git snapshot and tag workflows

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Git is optional for basic deployments, but strongly recommended for snapshots, tags, and safer rollbacks

## Installation

```bash
composer require m7md-5ttab/laravel-shared
```

Optional: publish the configuration file.

```bash
php artisan vendor:publish --tag=hosting-shared-config
```

Laravel package discovery will register:

- `php artisan hosting:check`
- `php artisan hosting:run`
- `php artisan hosting:rollback`

## Quick Start

Preview the deployment first:

```bash
php artisan hosting:run --dry-run --target=/home/account/public_html
```

Run the real deployment:

```bash
php artisan hosting:run --target=/home/account/public_html
```

Rollback the latest recorded deployment if needed:

```bash
php artisan hosting:rollback
```

If the package can already detect your `public_html` directory, you can omit `--target`.

## Typical Shared Hosting Layout

This package is designed for the common setup where your Laravel app lives outside the web root and only public assets are exposed:

```text
/home/account/
├── laravel-app/
└── public_html/
```

During deployment, the package syncs your Laravel `public/` directory into the target web root and patches `public_html/index.php` so it loads the app from the correct location.

## Commands

### `php artisan hosting:check`

Runs a readiness report without changing files.

It checks things like:

- PHP version and required extensions
- Laravel version
- Git availability and repository status
- `.gitignore` coverage
- symlink support
- `public/storage` status
- writable permissions for `storage` and `bootstrap/cache`
- shared-hosting target detection
- provider-specific hints for common shared hosts

Use this before your first deployment if you want a quick health check.

### `php artisan hosting:run`

This is the main workflow. It:

1. runs readiness checks
2. applies conservative automatic fixes inside your project
3. reruns readiness checks
4. shows a deployment summary
5. asks once before the real deployment
6. deploys using symlink or copy mode

Common options:

- `--symlink`
- `--copy`
- `--target=/absolute/path/to/public_html`
- `--tag`
- `--tag-name=custom-tag`
- `--dry-run`
- `--force`

Examples:

```bash
php artisan hosting:run --dry-run
php artisan hosting:run --copy
php artisan hosting:run --target=/home/account/public_html --tag
php artisan hosting:run --symlink --force
```

Useful notes:

- `--dry-run` previews automatic fixes and deployment actions without changing files
- `--force` skips the final deployment confirmation
- `--tag` and `--tag-name` require Git

What `hosting:run` can fix automatically:

- initialize Git when Git is installed and the project is not yet a repository
- offer to configure a local Git identity when `user.name` or `user.email` is missing for snapshot commits
- append missing Laravel-related `.gitignore` rules
- create `public/storage`
- apply writable permissions to Laravel runtime directories
- create the initial Git snapshot commit only when the workflow initialized the repository itself

What stays manual:

- installing Git on the machine
- adding a Git remote
- enabling symlink support at the hosting provider level
- choosing a valid deployment path when your hosting layout is unusual

### `php artisan hosting:rollback`

Use rollback in one of these ways:

- no arguments: clean the latest recorded deployment and remove package-managed artifacts
- `--snapshot=...`: clean a specific recorded deployment snapshot
- `--tag=...`: roll the Git repository back to a specific deployment tag

Examples:

```bash
php artisan hosting:rollback
php artisan hosting:rollback --snapshot=hosting-deploy-2026-05-14-001
php artisan hosting:rollback --tag=hosting-deploy-2026-05-14-003
php artisan hosting:rollback --tag=hosting-deploy-2026-05-14-003 --force
```

Notes:

- rollback always asks for confirmation
- `--force` allows rollback to continue even when Git has uncommitted changes
- tag-based rollback is for source-control recovery
- default and snapshot rollback are for cleaning deployment artifacts created by this package

## Deployment Modes

### Symlink Mode

Recommended when your host allows symlinks.

- syncs `public/` into the target web root
- patches `index.php` to load Laravel from the correct base path
- creates `public_html/storage` as a symlink to `storage/app/public`

### Copy Mode

Recommended when symlinks are disabled or unreliable.

- syncs `public/` into the target web root
- patches `index.php` to load Laravel from the correct base path
- copies public storage assets into `public_html/storage`

If you do not choose a mode, the package prefers symlink mode first and falls back to copy mode automatically when needed. If you explicitly pass `--symlink`, it will not silently switch to copy mode.

## Deployment Records

During real deployments, the package stores:

- `storage/app/hosting-shared/manifests`
- `storage/app/hosting-shared/backups`
- `storage/logs/hosting-shared.log`

These records are used for cleanup-based rollbacks and deployment history.

## Configuration

Publish the config if you want to customize behavior:

```bash
php artisan vendor:publish --tag=hosting-shared-config
```

Useful settings in `config/hosting-shared.php` include:

- `public_html_candidates`
- `permissions.directory_mode`
- `deployment.tag_prefix`
- `deployment.preserve_paths`

By default, `.well-known` is preserved in the target directory.

## Troubleshooting

### `public_html` was not detected

Pass the target explicitly:

```bash
php artisan hosting:run --target=/home/account/public_html
```

### Symlink deployment failed

Retry in copy mode:

```bash
php artisan hosting:run --copy
```

Some shared hosts disable symlink creation entirely.

### Git is unavailable

Basic deployment can still work, but Git snapshot commits and tag workflows will be skipped or blocked depending on the command options you use.

If you want deployment tags, install Git and rerun with `--tag` or `--tag-name`.

## Recommendations

- Keep the Laravel app outside `public_html` whenever your host allows it
- Run `--dry-run` before the first real deployment
- Prefer copy mode when symlink support is inconsistent on your host
- Keep `.well-known` preserved if your host uses it for SSL challenges
- Use Git tags when you want a clearer rollback history
