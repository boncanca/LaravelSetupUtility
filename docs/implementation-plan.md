# Laravel Setup Utility — implementation plan

Prepared 13 November 2026. Planning deliverable only; no repository, issue, PR, automation, or deployment changes made.

## Recommendation

Continue `boncanca/LaravelSetupUtility` as a standalone, disposable PHP configurator. Retain Symfony Console + Box rather than restarting with a Laravel application or forking a starter kit. Add Symfony Process and Laravel Prompts only where needed. Ship CLI first, then a temporary browser interface using the same engine.

The output is an ordinary Laravel application owned by its developer. No LSU dependency, service provider, route, tracking marker, branding, telemetry agent, or runtime integration remains in it. Selected application code, packages, engineering configuration and instructions deliberately remain. This distinction makes “nothing from the starter remains” testable without removing the features the developer selected.

Bound the first release to new applications and conservative additions to existing applications. Do not support frontend conversions, broad Laravel upgrades, infrastructure provisioning, arbitrary remote recipes, a package marketplace, live billing activation, or database changes in the core MVP.

## Current evidence and reconciliation

The 6 November `Laravel_Setup_Utility_Research.md` is useful architecture material, but its empty-repository/no-issues snapshot is superseded by GitHub.

| Surface | Observed state on 13 November | Planning implication |
| --- | --- | --- |
| main | `8a8dd8fac415991efa5b749a5af34eb41fe294d1`; README and Composer manifest describe packaging foundation | Do not call the installer usable yet |
| Draft PR #19 | `74bb584d87be2fa0d6c0176e0aade1131d78ea18`; open, unmerged | Reconcile this integration before adding new architecture |
| PR #19 branch | Inspection, deterministic Pint/PHPStan proposals, staged Composer solving, retained bundles, manifest/lock apply and recovery | Valuable partial implementation; not full dependency installation/configuration |
| PR #19 review API | No submitted reviews returned | Earlier component review claims do not establish final integration approval |
| PR #19 checks | Successful packaging checks returned | Packaging success does not certify new-app or foundation journeys |
| Issues | Architecture #2 closed; #1 and #3–#12 plus MVP tracker #16 open | Reuse and clarify existing backlog rather than recreate it |
| Runtime documentation | Utility PHP 8.3+, currently qualified Ubuntu 24.04/PHP 8.3.6 | Separate utility runtime from generated-app requirements |

The branch README explicitly states that creating Laravel applications, configuring engineering tools, and installing vendor dependencies are not implemented. The current resolver also rejects custom repositories and unsupported Composer settings. Preserve these honest limits until qualified support exists.

This review read repository material, selected upstream files and live GitHub metadata. It did not execute package installations or rerun the project tests. Reported historical tests remain repository evidence, not new verification by this review.

## Architecture boundary

Use one codebase and one pipeline: inspect → select → plan → resolve → review → apply → verify → clean.

Six concepts are enough:

1. **Project facts:** canonical path, manifests/locks, declared and installed versions distinguished, PHP/extensions, Composer/Node/package manager, Git state, existing tools and known configuration shapes. Inspect without bootstrapping the target first.
2. **Selections:** explicit presentation, authentication, optional admin panel, engineering and optional agent capabilities. Presets expand into visible selections.
3. **Recipes:** repository-owned, typed metadata and reviewed PHP steps; not a custom scripting language. Declare versions, constraints, prerequisites/conflicts, owned paths, patch strategy, verification and recovery limits.
4. **Immutable plan:** exact selections, recipe/catalog revision, input hashes, resolved locks, command argument arrays, file changes, retained dependencies and side effects. Changed inputs require a new plan.
5. **Runner:** serialized execution for one target, bounded processes, staging, progress, cancellation and exit codes. No model-generated shell execution.
6. **External journal:** private snapshots and recovery state outside the app, explicit retention and purge. It is not a hidden target marker.

Keep CLI commands thin. Planner and runner must not depend on terminal prompts or HTTP requests. No database, account service, always-on daemon, or separate orchestration platform is needed.

### Installation and recovery contract

- Distribute a PHAR with integrity information. An optional global Composer installation may live outside projects. Never require LSU in the target merely to execute setup.
- New apps: create in a sibling staging directory on the same filesystem, run selected setup and checks, then publish by rename when the destination is still absent.
- Existing apps: establish a clean baseline or explicit complete backup policy; apply only supported additions. Preserve handwritten providers, tests, scripts, MCP servers and configuration. Unsupported edits fail with an explanation.
- Resolve selected dependency constraints together. Respect existing locks; do not silently widen constraints, enable `minimum-stability=dev`, or unlock the entire dependency tree.
- Show dependency resolution separately from script/plugin execution. `--no-scripts --no-plugins` solving is not a sandbox and is not equivalent to a working installation. Review the execution phase explicitly.
- Extend the present two-file transaction carefully: vendor installation, application scripts and configuration publishing introduce distinct side effects. Do not promise universal rollback. Recover owned files only when they have not subsequently changed; report unresolved vendor, script, database or network effects.
- Rerun by inspecting actual state. Already satisfied steps are skipped; no duplicate provider registrations, scripts or dependencies.
- No database migrations, seeds, external provisioning, or paid actions by default. Local fixture migrations are explicit verification actions.
- Verify app boot, selected commands, assets and relevant behavior before success. Verify the disposal contract from a before/after inventory, manifests, routes and provider registrations. Normal completion removes temporary installer material; interrupted runs provide external recovery and cleanup instructions.

## Configuration model

| Axis | Supported choices | Boundary |
| --- | --- | --- |
| Presentation | Raw Laravel/Blade; Livewire; Inertia + Vue; Inertia + React; API-first | Foundation choice for new apps; existing stack retained |
| Authentication | None; appropriate upstream session starter; Sanctum cookies or bearer tokens for API use | Livewire selection alone must not imply auth scaffolding |
| Admin | None; Filament | Overlay, not an alternative frontend architecture; brings Livewire dependencies and may coexist with Inertia |
| Engineering | Recommended strict preset; individual tool selection | Show retained packages and runtime behavior separately |
| Agents | None; optional Boost and explicitly supported client configuration | Core fully functional without AI |
| SaaS, later | Single tenant; organization-aware shared database; isolated tenancy subsequently | Billing, permissions and tenancy are independent selections |

Start with one qualified Laravel/PHP target pair before multiplying recipes. Laravel 13/PHP 8.4 is the desired target, subject to current upstream constraints and actual generated-app qualification. Do not equate it with the utility's PHP 8.3 execution floor. Nuno's current main Composer manifest requires PHP `^8.5.0` and Laravel `^13.33.0`; copying main wholesale would raise the target floor. Pin reviewed upstream references and qualify an appropriate tool set for the intended target instead.

Proposed CLI after completion (not currently implemented as a whole): `doctor`, `new`, `configure`, `plan`, `apply`, `verify`, `recover`, and later `serve`. Preserve or deprecate the existing detailed commands deliberately. Support interactive prompts, noninteractive configuration, JSON output, useful exit codes and resumable diagnostics. Missing mandatory answers fail in noninteractive mode rather than guessing.

Stable and Dev refer to utility/catalog channels. Resolve a channel to one immutable revision per run. Dev does not automatically mean prerelease Laravel or dependencies. Record exact utility/catalog/recipe/starter versions, runtime/toolchain and final locks; selections alone cannot reproduce a moving dependency ecosystem.

## Engineering constitution and defaults

Generate `ENGINEERING.md` as the human-readable constitution and keep `AGENTS.md` as the conventional agent entry point referencing it, with stack-specific commands and boundaries. Two files have distinct purposes; do not duplicate the full policy. Preserve existing instructions and any Boost-managed block. If a project already centralizes its constitution in AGENTS.md, offer to retain that organization.

Suggested constitution text:

> Simplicity scales. Complexity fails. Prefer the smallest readable solution that meets correctness, security and performance needs. Follow Laravel conventions before adding abstractions. Validate inputs, authorize every protected action, protect tenant boundaries, use transactions where consistency requires them, and test observable behavior. Keep dependencies intentional. Run the documented checks before declaring work complete. Preserve user changes and report limitations honestly. Never commit secrets or perform destructive database or production actions as part of ordinary setup.

Record actual versions, directory boundaries, commands, database assumptions and exception process underneath this policy. Machine-checkable gates belong in tool configuration and CI; Markdown alone enforces nothing.

| Default | New project recommendation | Existing project treatment |
| --- | --- | --- |
| Types | Strict declarations in generated first-party PHP; explicit signatures | New/touched supported files; no silent bulk rewrite |
| Pint | Versioned rules and check command | Merge supported configuration or show conflict |
| Larastan/PHPStan | Highest qualified level the template passes; no fabricated baseline | Preserve level/baseline; record pre-existing failures and prevent new regressions |
| Pest | Behavioral tests for generated features | Preserve PHPUnit unless migration explicitly selected |
| Rector + Laravel rules | Dry-run in checks; separate deliberate fix command | Never auto-refactor arbitrary source during setup |
| CI | Same local `composer check` gates; asset checks only when selected | Preserve existing workflow and scripts |
| Dependency audit | Composer audit and selected frontend audit, with explicit exceptions policy | Report findings; do not silently upgrade everything |
| Coverage | Useful behavior and negative authorization tests | No blanket 100% requirement masquerading as correctness |

`composer check` should run non-mutating format checks, static analysis, Rector dry-run and tests, plus frontend type/build checks when relevant. Keep `composer fix` distinct. Avoid replacing a project's existing `composer test` behavior without review.

### Nuno Maduro Essentials

Treat Essentials as an explicitly selected application runtime dependency, not a transient setup dependency. Its README warns that it changes Laravel behavior and recommends new-project adoption. Include it in the recommended new-app strict preset with a visible feature summary; existing-app adoption requires staged behavioral validation.

Use an explicit published configuration: strict-model behavior, immutable dates, production-safe console behavior, and test isolation settings qualified against the template. Keep globally unguarded models disabled. Assess eager-loading and strict-model interaction, HTTPS/proxy behavior and environment-specific settings rather than blindly enabling everything. Preserve explicit Eloquent loading and authorization practices. Imported Pint/Rector configurations are reference material; review changes before applying them.

Do not copy Nuno's automatic requirement-bumping scripts, forced migrations, global `final` rule or exact 100% coverage gates as universal defaults. Final/readonly remain useful where framework extension and application behavior permit them.

## Ideas to adopt from reviewed repositories

| Source | Best idea to adopt | Adaptation for LSU |
| --- | --- | --- |
| Laravel installer | Upstream stack selection, blank versus authenticated kits, normal package-manager workflows | Reuse reviewed upstream scaffolds in staging; inspect hooks rather than replicate a framework installer |
| nunomaduro/laravel-starter-kit | Cohesive strict tooling and one-command verification | Extract compatible engineering recipes; do not fork its full application or track moving main |
| nunomaduro/essentials | Explicit better Laravel runtime defaults | Retain only when selected, publish reviewed settings and verify behavior |
| nunophp/style | Guided selection of Pest/Pint/PHPStan/Rector and useful Composer scripts | Implement externally; do not leave its auto-discovered setup provider or replace AppServiceProvider wholesale |
| CorentinSarda/starter-kit | A cohesive strict preset | Its current README points to Nuno's kits; treat as overlapping reference, not an independent compatibility guarantee |
| ChrisTitusTech/winutil | Understandable presets and Stable/Dev channels | Use pinned catalogs and transparent selections; no administrator requirement or download-and-execute shell model |
| Laravel Zero | Standalone PHP CLI/PHAR alternative | Existing component-based decision already made; reconsider only for a demonstrated maintenance problem |
| Liberu boilerplate material | Composable modules/themes and distinct panels | Inspiration for optional recipes; no wholesale architecture or module framework in default apps |
| Spatie Laravel Multitenancy | Explicit tenant context and configurable switching tasks | Candidate when context propagation is needed; qualify one adapter before exposing more |
| stancl/tenancy | Automatic tenant identification and infrastructure bootstrapping | Later isolated-tenancy strategy, not a dependency for the shared-database MVP |

Liberu's exact previously referenced repository path could not be refreshed at the tested raw URLs. Its row is based on earlier project material and must not be treated as current compatibility evidence. Likewise, earlier generic references to ProstarterKit, Docker/environment bundles, Mix and Herd downloads lack exact repository identities in the current source material; no code adoption or compatibility claim is based on them. Filapanel is the product analogy, not a reviewed source-code dependency. Verify licenses and record source commits before copying any implementation. The LSU repository currently has no selected open-source license; resolve this before distribution.

## SaaS and multitenancy configurators

Defer implementation until the installer core is qualified, but reserve separate recipe boundaries now:

1. **Single tenant:** ordinary app; no tenant package.
2. **Organization-aware SaaS:** organizations, memberships, switching and policy enforcement; one database with explicit tenant ownership. Start here for the first tenancy release. No billing or domains implied.
3. **Isolated tenancy:** separate strategy review for database connections, provisioning, domains and lifecycle. Compare Spatie and stancl against requirements; do not install both or promise automatic conversion from shared rows.
4. **Billing:** independent provider-specific recipe after tenancy. Identify billable entity, subscription/entitlement model, verified webhook processing and idempotency. No live credentials, purchases or provisioning during setup.
5. **Permissions:** native policies first; optional Spatie Permission and qualified Filament Shield integration. Roles alone do not establish tenant isolation.

Filament panel tenancy is one interface to tenant context, not whole-application security. Qualify API/controllers, background jobs, CLI, exports, search, route binding, validation, cache, files, notifications and long-lived worker context reset. Cross-tenant negative tests are a release gate. Do not advertise a general SaaS conversion tool after merely generating Organization and Membership models.

## Later welcome-screen GUI

Launch an external temporary loopback server with `serve`. Use Laravel-welcome-inspired bundled HTML/CSS and minimal JavaScript. It displays exactly the same selections, plan, retained dependencies, diffs, progress and verification results as the CLI. Execution remains in the shared runner.

Do not mount a setup route in the app or require the target to boot. Bind loopback, validate Host/Origin, use a one-time session bootstrap and CSRF protection, constrain paths, redact output, serialize mutations and terminate on completion/idle timeout. Browser closure must leave a recoverable runner state. CLI/GUI plan equivalence is testable. No Filament/SPA framework is needed merely to build this installer UI.

## Phased roadmap

| Phase | Deliverable | Exit gate |
| --- | --- | --- |
| 0 — Reconcile | Review main and PR #19 at exact SHAs, refresh acceptance evidence and licensing decision | Known delta documented; final integration review and separate QA evidence; no completion claim based on packaging alone |
| 1 — Reliable CLI core | Inspection, immutable plans, retained resolution, bounded dependency/config apply, recovery | Real existing-app journey; failure/cancellation/drift/idempotency tests; explicit recovery limits; zero installer residue |
| 2 — First useful release | New raw Laravel + strict engineering + constitution; optional Essentials | Generated app boots and checks pass; unsupported runtime rejected before mutation; developer can continue without LSU |
| 3 — Ecosystem foundations | Livewire, Filament overlay, Inertia Vue/React, API/Sanctum; optional Boost/client configs | Every advertised combination generated and verified; preserved existing-app configuration; authorization negatives pass |
| 4 — Release qualification | Stable/Dev immutable catalogs, integrity, documented platform matrix, onboarding trials | Reproducible locked fixtures; supported platforms evidenced; no secrets/residue; usable release artifact |
| 5 — Optional extensions | GUI and narrow organization tenancy as separate workstreams | GUI shares engine and passes lifecycle checks; tenant isolation passes all declared surfaces |
| 6 — Subsequent scope | Billing, isolated tenancy, optional trusted-catalog AI recommendations | Separate architecture/provider/privacy decisions and feature-specific acceptance |

Sequence by exit gates rather than promising dates before the current integration is reviewed. Keep existing #16 as MVP delivery tracker. Core MVP ends at phase 4; GUI, tenancy and remote AI remain later scope.

## GitHub-ready backlog

The following are proposed issue-body refinements mapped to existing issues; none have been submitted. Proposed labels are suggestions, not existing labels. All implementation items require feature branches, behavior-focused tests, independent engineering review and separate QA on the final reviewed SHA. Record commands/results, reviewed SHA, QA SHA, supported matrix and material limitations. Reuse historical evidence only when its scope and tree remain applicable.

### R0 — Reconcile final integration and acceptance evidence

Destination: existing #16 and PR #19 review checklist. Priority P0; labels `type:verification`, `priority:p0`; dependencies none.

Outcome: establish an honest release baseline without duplicating prior work.

- [ ] Compare main and PR #19 and record exact reviewed commit and implemented boundaries.
- [ ] Verify source/PHAR suites, packaging reproducibility and a real Laravel manifest apply/recover journey on the final candidate.
- [ ] Complete independent engineering review and separate QA; list findings and remaining work under #4–#9.
- [ ] Preserve draft/release status until acceptance is satisfied; merge is a separate authorized action.

### R1 — Finish runtime and distribution qualification

Destination: #1. Priority P0; labels `type:build`, `priority:p0`; depends R0 for final integration qualification.

- [ ] PHAR runs outside checkout/vendor from a path containing spaces and reports clear missing-runtime errors.
- [ ] Document utility runtime separately from generated-app toolchain requirements.
- [ ] Verify two builds in the same locked environment; publish integrity/provenance data for the release process.
- [ ] Qualify Linux first; mark macOS, native Windows and WSL unsupported until independently exercised.
- [ ] Record owner-selected distribution license and third-party notices before public release.

### R2 — Complete project facts and compatibility planner

Destination: #4. Priority P0; labels `type:core`, `priority:p0`; depends R0.

- [ ] Detect extensions, Composer/Node/package manager, Git status and known configuration; distinguish declarations from installed state.
- [ ] Typed recipes declare supported edits, conflicts, verification and output ownership.
- [ ] Pin full catalog and upstream references; retain solved manifests/locks and record relevant environment inputs.
- [ ] Plan/conflict paths leave target bytes unchanged; altered inputs invalidate apply.
- [ ] Unsupported Composer settings and recipe combinations fail explicitly; solver success remains distinct from integration qualification.

### R3 — Extend file transactions to usable installation and recovery

Destination: #5. Priority P0; labels `type:core`, `priority:p0`; depends R2.

- [ ] Install selected dependencies from retained locks and apply supported configuration patches in staged execution.
- [ ] Declare script/plugin execution and recovery boundaries before apply.
- [ ] Inject failures before/between writes, during dependency installation, verification and cancellation; record residual effects.
- [ ] Reruns are idempotent, concurrent cooperating runs are refused, and user-edited/untracked files are preserved.
- [ ] External journal supports guarded recovery and explicit purge; completion leaves no installer runtime or marker in target.

### R4 — Deliver new raw Laravel and strict engineering vertical slice

Destination: #6. Priority P1; labels `type:feature`, `area:engineering`, `priority:p1`; depends R3.

- [ ] Generate in sibling staging and publish only after verification; existing destination is never overwritten.
- [ ] Select Pest/Pint/Larastan/Rector/CI and generate non-mutating `composer check` without clobbering scripts.
- [ ] Generate ENGINEERING.md and AGENTS.md with actual stack/commands, preserving existing policy.
- [ ] Essentials is a visible runtime choice with explicit settings; globally unguarded models remain disabled.
- [ ] Real target app boots and all selected checks pass; unselected dependencies and LSU artifacts are absent.
- [ ] Existing PHPUnit suites and configurations remain intact; baseline failures are reported distinctly.

### R5 — Add qualified presentation, admin and auth recipes

Destination: #7. Priority P1; labels `type:feature`, `area:recipes`, `priority:p1`; depends R4.

- [ ] Add Livewire with/without auth, Inertia Vue/React, Filament overlay and API auth selections.
- [ ] Reuse pinned upstream scaffolding; detect conflicting providers, routes and frontend configuration before apply.
- [ ] Build/test each advertised combination, including one supported Inertia + Filament fixture.
- [ ] API fixtures test unauthenticated access, forbidden actions and Sanctum mode boundaries.
- [ ] Existing-app frontend conversion remains unsupported and fails before target mutation.

### R6 — Add optional Boost and client-native agent configuration

Destination: #8. Priority P1; labels `type:feature`, `area:agents`, `priority:p1`; depends R4.

- [ ] Offer explicit Boost retention and supported client destinations; do not assume universal mcp.json compatibility.
- [ ] Merge configuration and preserve handwritten AGENTS instructions and existing MCP entries.
- [ ] Validate each supported client config; use environment references and redact secrets.
- [ ] Core install and verification work with no AI selected; no remote recommendations in this issue.

### R7 — Qualify catalogs and end-to-end release matrix

Destination: #9. Priority P1; labels `type:release`, `priority:p1`; depends R5 and R6.

- [ ] Stable/Dev resolve to immutable snapshots; no accidental prerelease dependency opt-in.
- [ ] Execute new/existing-app journeys and all advertised combinations using real upstream packages and recorded locks.
- [ ] Exercise interruption, recovery, rerun and disposal checks from the shipped PHAR.
- [ ] Publish supported runtime/platform combinations and known limitations; independent review/QA reference release SHA.
- [ ] Mark #16 complete only after a usable artifact and all required journeys pass.

### R8 — Validate developer onboarding

Destination: #3. Priority P1; labels `type:research`, `area:ux`; depends approved configuration model; can proceed alongside core implementation.

- [ ] Test choices/journeys with 5–8 developers including experienced and AI-assisted Laravel users.
- [ ] Record observed confusion separately from hypotheses, especially retained tools versus installer residue.
- [ ] Measure completion, recovery comprehension and selection time excluding downloads; use evidence to refine prompts.

### R9 — Add temporary welcome-style browser UI

Destination: #10. Priority P2; labels `type:feature`, `area:gui`; depends R7 and R8.

- [ ] CLI and GUI produce equivalent plans and share the runner.
- [ ] Validate accessible selection/progress/errors, path restrictions, session/CSRF/Host/Origin controls and shutdown behavior.
- [ ] No routes/providers/dependencies are injected into target; closing browser leaves recoverable state.

### R10 — Qualify organization tenancy as a bounded configurator

Destination: #11. Priority P2; labels `type:feature`, `area:tenancy`; depends R7 and a separate tenancy strategy decision.

- [ ] Record tenant model, membership, switching, policies and supported surfaces before generation.
- [ ] Keep billing and permissions independently selectable; choose at most one tenant-context adapter.
- [ ] Cross-tenant reads/writes, route binding, validation, jobs, cache, files, exports and worker reset tests pass.
- [ ] No production migration, domain provisioning, billing activation or automatic isolation-strategy conversion.

### R11 — Optional trusted-catalog AI recommendations

Destination: #12. Priority P3; labels `type:feature`, `area:ai`; depends R7 and provider/privacy review.

- [ ] Model output is recipe IDs/explanations only, validated by the same deterministic planner.
- [ ] Unknown/incompatible recipes and prompt-injection attempts are rejected.
- [ ] No arbitrary command execution; no secrets or source upload by default; offline core unaffected.

No new GitHub issues are necessary to express this plan. If approved later, refine existing issue bodies and cross-links first; create child issues only where work is independently reviewable and not already represented.

## Sources

Current project: https://github.com/boncanca/LaravelSetupUtility ; main README and composer.json; integration/mvp1-reconcile README and docs/architecture.md; issues #1–#12 and #16; draft PR #19, its reviews and commit check-runs.

Earlier material: Laravel_Setup_Utility_Research.md, dated 6 November 2026, read in full; prior project decisions retrieved for repository identification. Current GitHub supersedes its status snapshot.

Primary upstream files reviewed:

- https://github.com/laravel/installer/blob/master/src/NewCommand.php
- https://github.com/nunomaduro/laravel-starter-kit/blob/main/composer.json
- https://github.com/nunomaduro/essentials/blob/main/README.md
- https://github.com/nunophp/style/blob/main/README.md
- https://github.com/CorentinSarda/starter-kit/blob/main/README.md
- https://github.com/ChrisTitusTech/winutil/blob/main/README.md
- https://github.com/spatie/laravel-multitenancy/blob/main/README.md
- https://github.com/archtechx/tenancy/blob/3.x/README.md

These are source/documentation observations and architecture recommendations, not a tested compatibility matrix. Pin exact upstream revisions during recipe qualification.
