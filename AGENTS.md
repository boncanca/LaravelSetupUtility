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

Current scope: packaging foundation and read-only manifest inspection; planning/application installation is not implemented.
