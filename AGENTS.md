# Engineering instructions

Simplicity scales. Complexity fails. Use the minimum sufficient readable, correct and secure code.

- Keep the utility outside target applications. No installer dependency, route or provider may remain in a target.
- Use one inspect/plan/apply/verify engine for CLI and future browser UI.
- Prefer upstream Laravel installers and conventions. Add dependencies only when used.
- Test behavior before implementing it. Preserve evidence of expected failures and passing results.
- Run `composer test`, `composer build`, PHAR CLI tests and archive inspection for packaging changes.
- Require independent engineering review and separate QA before marking behavior work complete.
- Do not run target migrations, seeds, remote provisioning or destructive Git commands by default.
- Do not claim compatibility or rollback guarantees without execution evidence.
- Keep stable/dev catalog selection distinct from upstream prerelease package selection.
- Keep secrets and target data out of repository, diagnostics and AI requests.

Current scope: packaging, inspection/planning, isolated Composer resolution, retained private bundles and bounded manifest/lock transactions with recovery. The existing-app resolve/apply engine does not install vendor dependencies or execute application scripts. The separate `create` journey installs the pinned official Laravel skeleton in external private staging, with Composer scripts/plugins disabled, then runs explicitly qualified Laravel initialization and verification commands before publishing to an absent destination. Existing-app planning/resolution/transaction outputs retain `apply_ready: false` for full application readiness; file-only apply reports `dependencies_installed: false`. Transaction changes require failure-injection, exact restoration and user-edit refusal tests. Resolver tests distinguish synthetic fixtures from actual upstream integration evidence.
