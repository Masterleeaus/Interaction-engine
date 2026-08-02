# Execution Engine Architecture

The Interaction Engine is a universal execution layer for all Titan Zero interactions:

- Wizards
- Voice interactions
- Checklists
- Forms
- SOPs
- AI conversations
- Generated workflows (Titan Sprout)

## Core Components

1. **Compiler** – Validates and compiles interaction definitions from JSON/YAML.
2. **Registry** – Provides compiled definitions to the runtime.
3. **Runtime** – Orchestrates the interaction flow.
4. **Pipeline** – Middleware stack for processing interactions.
5. **Context** – Builds rich context for each interaction.
6. **Resolver** – Intelligently resolves answers (ask, infer, lookup, etc.).
7. **Capabilities** – Maps interactions to business capabilities.
8. **Policies** – Enforces business rules and permissions.
9. **Commands** – Executes domain commands.
10. **Events** – Records audit trail and enables replay.
11. **Offline** – Queues commands for offline sync.

## Design Principles

- **Immutable DTOs** – All definition objects are readonly.
- **Capability-driven** – Interactions specify capabilities, not commands.
- **Pipeline-based** – Request processing through middleware.
- **Plugin-friendly** – All major components are interface-driven.
- **Knowledge-aware** – Resolvers can query knowledge sources.
- **Renderer-agnostic** – UI can be Blade, Vue, React, or native.