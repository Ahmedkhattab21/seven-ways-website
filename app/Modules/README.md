# Application modules

Each business capability belongs in `app/Modules/<ModuleName>` when it is implemented.
A module may contain its own Actions, DTOs, Http, Models, Policies, Services, and tests,
but should only create folders that contain working code.

Shared framework-independent rules belong in `app/Core`. Cross-module orchestration
belongs in application services, not Eloquent models or controllers.

Planned capabilities are documented in `docs/architecture.md`; their tables and code
are intentionally not scaffolded during phase 01.
