# Laravel fixture verification

Session evidence reported by the coordinating agent on 2026-11-07; final candidate QA is recorded in the corresponding PR/issue.

| Check | Actual result |
| --- | --- |
| Real application source | laravel/laravel skeleton v12.12.2; Laravel framework 12.71.0 |
| Public Composer staged resolution | Existing locked application plus PHPStan resolved from public Packagist to PHPStan 2.3.5; 113 packages reported |
| Retained bundle and utility apply | Private external bundle created; first apply reported manifests_applied; repeat reported already_applied |
| Utility recovery | Reported manifests_recovered; original composer.json and composer.lock restored byte for byte |
| Separate manual installation | Composer install --no-scripts --no-plugins --prefer-dist from the applied lock installed PHPStan 2.3.5, with zero updates/removals |
| Application checks after manual installation | Artisan reported Laravel 12.71.0; PHPStan reported 2.3.5; PHPUnit passed 2 tests and 2 assertions |

The utility applied Composer files only. The coordinating agent separately installed dependencies and prepared the disposable application environment/key for verification. No vendor installation, application setup, migration or script execution is implied by utility transaction success. These small skeleton tests do not establish broad application, platform, recipe configuration or tenancy compatibility.

Automated offline coverage additionally checks artifact tampering, lock/manifest drift, exclusive locking, inward symlink rejection, failure after the first write, absent/existing lock restoration, interrupted mixed-state recovery, idempotence, preservation of untracked files and refusal to overwrite user edits.
