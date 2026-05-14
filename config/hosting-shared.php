<?php

declare(strict_types=1);

use M7md5ttab\LaravelShared\Checkers\BootstrapCacheWritableCheck;
use M7md5ttab\LaravelShared\Checkers\GitIgnoreCheck;
use M7md5ttab\LaravelShared\Checkers\GitIdentityCheck;
use M7md5ttab\LaravelShared\Checkers\GitInstalledCheck;
use M7md5ttab\LaravelShared\Checkers\GitRemoteCheck;
use M7md5ttab\LaravelShared\Checkers\GitRepositoryCheck;
use M7md5ttab\LaravelShared\Checkers\HostingProviderCheck;
use M7md5ttab\LaravelShared\Checkers\LaravelVersionCheck;
use M7md5ttab\LaravelShared\Checkers\OperatingSystemCheck;
use M7md5ttab\LaravelShared\Checkers\PhpExtensionsCheck;
use M7md5ttab\LaravelShared\Checkers\PhpVersionCheck;
use M7md5ttab\LaravelShared\Checkers\PublicHtmlCheck;
use M7md5ttab\LaravelShared\Checkers\StorageLinkCheck;
use M7md5ttab\LaravelShared\Checkers\StorageWritableCheck;
use M7md5ttab\LaravelShared\Checkers\SymlinkSupportCheck;
use M7md5ttab\LaravelShared\Fixers\GitIgnoreFixer;
use M7md5ttab\LaravelShared\Fixers\GitIdentityFixer;
use M7md5ttab\LaravelShared\Fixers\GitInstallFixer;
use M7md5ttab\LaravelShared\Fixers\GitRepositoryFixer;
use M7md5ttab\LaravelShared\Fixers\WritablePermissionsFixer;
use M7md5ttab\LaravelShared\Publishers\CopyPublisher;
use M7md5ttab\LaravelShared\Publishers\SymlinkPublisher;
use M7md5ttab\LaravelShared\Rollback\BackupRollbackStrategy;
use M7md5ttab\LaravelShared\Rollback\GitRollbackStrategy;

return [
    'required_php_version' => '8.2.0',
    'minimum_laravel_version' => '11.0.0',

    'required_php_extensions' => [
        'ctype',
        'fileinfo',
        'json',
        'mbstring',
        'openssl',
        'pdo',
        'tokenizer',
        'xml',
    ],

    'gitignore_rules' => [
        '/vendor',
        '/node_modules',
        '/storage/*.key',
        '/bootstrap/cache/*.php',
        '.env',
        '.env.backup',
        '.phpunit.result.cache',
    ],

    'public_html_candidates' => [
        '../public_html',
        '../../public_html',
        'public_html',
    ],

    'permissions' => [
        'directory_mode' => 0775,
    ],

    'storage_public_directory' => 'storage/app/public',
    'public_storage_link' => 'public/storage',

    'deployment' => [
        'manifest_directory' => 'app/hosting-shared/manifests',
        'backup_directory' => 'app/hosting-shared/backups',
        'log_file' => 'logs/hosting-shared.log',
        'tag_prefix' => 'hosting-deploy',
        'preserve_paths' => [
            '.well-known',
        ],
    ],

    'provider_signatures' => [
        'cpanel' => ['cpanel', 'public_html', '/home/', 'ea-php'],
        'hostinger' => ['hostinger', 'hpanel'],
        'namecheap' => ['namecheap', 'supersonic'],
        'godaddy' => ['godaddy', 'secureserver'],
        'directadmin' => ['directadmin'],
    ],

    'provider_guidance' => [
        'cpanel' => 'cPanel deployments usually work best when your Laravel app sits beside public_html and the public entry point is copied into public_html.',
        'hostinger' => 'Hostinger may disable symlink support. If storage links fail, remove symlink from disabled PHP functions or use copy mode.',
        'namecheap' => 'Namecheap shared hosting often expects apps outside public_html with only public assets copied into public_html.',
        'godaddy' => 'GoDaddy shared hosting commonly limits symlink usage. Prefer copy mode unless you have confirmed symlink support.',
        'directadmin' => 'DirectAdmin environments vary by host. Verify symlink support and writable permissions before deploying.',
        'generic' => 'If your provider blocks symlinks, use copy mode and copy storage assets into public_html/storage during deployment.',
    ],

    'checks' => [
        GitInstalledCheck::class,
        GitRepositoryCheck::class,
        GitRemoteCheck::class,
        GitIdentityCheck::class,
        GitIgnoreCheck::class,
        SymlinkSupportCheck::class,
        StorageLinkCheck::class,
        PhpVersionCheck::class,
        PhpExtensionsCheck::class,
        LaravelVersionCheck::class,
        OperatingSystemCheck::class,
        HostingProviderCheck::class,
        PublicHtmlCheck::class,
        StorageWritableCheck::class,
        BootstrapCacheWritableCheck::class,
    ],

    'fixers' => [
        GitInstallFixer::class,
        GitRepositoryFixer::class,
        GitIdentityFixer::class,
        GitIgnoreFixer::class,
        WritablePermissionsFixer::class,
    ],

    'publishers' => [
        'copy' => CopyPublisher::class,
        'symlink' => SymlinkPublisher::class,
    ],

    'rollback_strategies' => [
        GitRollbackStrategy::class,
        BackupRollbackStrategy::class,
    ],
];
