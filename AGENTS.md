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

Current scope: packaging, inspection/planning, isolated Composer resolution, retained private bundles and bounded manifest/lock transactions with recovery. The utility does not install vendor dependencies or execute application scripts. All outputs retain `apply_ready: false` for full application readiness; file-only apply reports `dependencies_installed: false`. Transaction changes require failure-injection, exact restoration and user-edit refusal tests. Resolver tests distinguish synthetic fixtures from actual upstream integration evidence.

New-project scope: fixed raw Laravel skeleton, scripts/plugins disabled, local environment, explicit upstream boot/routes/tests, no migrations/frontend setup. Publish only after verification; failure leaves destination absent. Preserve tests distinguishing synthetic runner fixtures from real upstream generated-app qualification.
