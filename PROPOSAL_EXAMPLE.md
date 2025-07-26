# EdgeBinder Entity Integration Examples

## Approach 1: Binding-Aware Entities (Recommended)

### Workspace Entity with Project Bindings

```php
<?php

namespace Domain\Workspace\Entity;

use EdgeBinder\EdgeBinderInterface;
use Domain\Project\Entity\Project;

class Workspace
{
    private ?string $id = null;
    private ?string $name = null;
    private ?EdgeBinderInterface $binder = null;

    // Standard entity methods...
    public function getId(): ?string { return $this->id; }
    public function getName(): ?string { return $this->name; }

    // EdgeBinder injection (optional - for when you need bindings)
    public function setBinder(EdgeBinderInterface $binder): self
    {
        $this->binder = $binder;
        return $this;
    }
    
    // Business methods that create bindings
    public function addProject(Project $project, array $metadata = []): void
    {
        if (!$this->binder) {
            throw new \RuntimeException('EdgeBinder not injected');
        }

        $this->binder->bind(
            from: $this,
            to: $project,
            type: 'has_project',
            metadata: array_merge([
                'added_at' => new \DateTimeImmutable(),
                'access_level' => 'read',
                'is_primary' => false
            ], $metadata)
        );
    }
    
    public function getProjects(array $filters = []): array
    {
        if (!$this->binder) {
            return [];
        }

        $query = $this->binder->query()
            ->from($this)
            ->type('has_project');

        // Apply filters to binding metadata
        foreach ($filters as $key => $value) {
            $query->where($key, $value);
        }

        return $query->get();
    }
    
    public function getProjectAccess(Project $project): ?array
    {
        if (!$this->binder) {
            return null;
        }

        $binding = $this->binder->query()
            ->from($this)
            ->to($project)
            ->type('has_project')
            ->first();

        return $binding ? $binding->getMetadata() : null;
    }
    
    public function updateProjectAccess(Project $project, array $metadata): void
    {
        if (!$this->binder) {
            throw new \RuntimeException('EdgeBinder not injected');
        }

        $binding = $this->binder->query()
            ->from($this)
            ->to($project)
            ->type('has_project')
            ->first();

        if ($binding) {
            $this->binder->updateMetadata(
                $binding->getId(),
                array_merge($binding->getMetadata(), $metadata)
            );
        }
    }
    
    // Convenience methods for common operations
    public function getPrimaryProjects(): array
    {
        return $this->getProjects(['is_primary' => true]);
    }

    public function getWritableProjects(): array
    {
        return $this->getProjects(['access_level' => 'write']);
    }

    public function getProjectsByType(string $type): array
    {
        return $this->getProjects(['project_type' => $type]);
    }
}
```

## Approach 2: Repository Pattern with EdgeCraft

```php
<?php

namespace Domain\Workspace\Repository;

use Domain\Workspace\Entity\Workspace;
use Domain\Project\Entity\Project;
use EdgeCraft\EdgeCraftInterface;

class WorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function __construct(
        private WorkspaceRepositoryInterface $baseRepository,
        private EdgeCraftInterface $edgeCraft
    ) {}

    public function findById(string $id): ?Workspace
    {
        $workspace = $this->baseRepository->findById($id);

        if ($workspace) {
            // Inject EdgeCraft for edge operations
            $workspace->setEdgeCraft($this->edgeCraft);
        }

        return $workspace;
    }
    
    public function findWithProjects(string $workspaceId): ?Workspace
    {
        $workspace = $this->findById($workspaceId);

        if (!$workspace) {
            return null;
        }

        // Pre-load project edges for performance
        $projects = $this->edgeCraft->query()
            ->from($workspace)
            ->type('has_project')
            ->with(['to_entity']) // Eager load the actual project entities
            ->get();

        // Could cache this data or use it to populate a DTO
        return $workspace;
    }
}
```

## Approach 3: Domain Service for Complex Edge Operations

```php
<?php

namespace Domain\Workspace\Service;

use Domain\Workspace\Entity\Workspace;
use Domain\Project\Entity\Project;
use EdgeCraft\EdgeCraftInterface;

class WorkspaceProjectService
{
    public function __construct(
        private EdgeCraftInterface $edgeCraft
    ) {}
    
    public function linkProject(
        Workspace $workspace,
        Project $project,
        string $accessLevel = 'read',
        array $additionalMetadata = []
    ): void {
        $metadata = array_merge([
            'access_level' => $accessLevel,
            'linked_at' => new \DateTimeImmutable(),
            'linked_by' => $this->getCurrentUserId(), // From context
            'is_primary' => false,
            'project_type' => $project->getType(),
            'project_status' => $project->getStatus(),
            'created_at' => $project->getCreatedAt(),
        ], $additionalMetadata);

        $this->edgeCraft->craft(
            from: $workspace,
            to: $project,
            type: 'has_project',
            metadata: $metadata
        );
    }
    
    public function updateProjectAccess(
        Workspace $workspace,
        Project $project,
        string $newAccessLevel,
        ?string $reason = null
    ): void {
        $edge = $this->edgeCraft->query()
            ->from($workspace)
            ->to($project)
            ->type('has_project')
            ->first();

        if (!$edge) {
            throw new \DomainException('Project not linked to workspace');
        }

        $this->edgeCraft->updateMetadata($edge->getId(), [
            'access_level' => $newAccessLevel,
            'access_updated_at' => new \DateTimeImmutable(),
            'access_updated_by' => $this->getCurrentUserId(),
            'access_update_reason' => $reason,
            'previous_access_level' => $edge->getMetadata()['access_level'] ?? null
        ]);
    }
    
    public function getProjectStats(Workspace $workspace): array
    {
        $stats = $this->edgeCraft->aggregate()
            ->from($workspace)
            ->type('has_project')
            ->count('total_projects')
            ->countWhere('access_level', 'write', 'writable_projects')
            ->countWhere('is_primary', true, 'primary_projects')
            ->groupBy('project_type')
            ->get();

        return $stats;
    }
    
    public function findSimilarWorkspaces(Workspace $workspace, float $threshold = 0.8): array
    {
        // Find workspaces with similar project relationships
        return $this->edgeCraft->query()
            ->type('workspace_similarity')
            ->where('similarity_score', '>=', $threshold)
            ->where('computed_from', $workspace->getId())
            ->orderBy('similarity_score', 'desc')
            ->limit(10)
            ->get();
    }
}
```

## Approach 4: Event-Driven Relationship Updates

```php
<?php

namespace Domain\Workspace\EventHandler;

use Domain\Project\Event\ProjectUpdated;
use EdgeCraft\EdgeCraftInterface;

class UpdateWorkspaceProjectMetadata
{
    public function __construct(
        private EdgeCraftInterface $edgeCraft
    ) {}

    public function handle(ProjectUpdated $event): void
    {
        // Find all workspaces linked to this project
        $edges = $this->edgeCraft->query()
            ->to($event->project)
            ->type('has_project')
            ->get();

        foreach ($edges as $edge) {
            // Update edge metadata when project changes
            $this->edgeCraft->updateMetadata($edge->getId(), [
                'project_status' => $event->project->getStatus(),
                'project_type' => $event->project->getType(),
                'last_updated_at' => $event->project->getUpdatedAt(),
                'metadata_updated_at' => new \DateTimeImmutable()
            ]);
        }
    }
}
```

## Usage Examples

```php
// In your application/controller layer
$workspace = $workspaceRepository->findById($workspaceId);
$project = $projectRepository->findById($projectId);

// Add project with metadata
$workspace->addProject($project, [
    'access_level' => 'write',
    'is_primary' => true,
    'added_by' => $currentUser->getId(),
    'project_role' => 'main_project'
]);

// Get projects with specific access
$writableProjects = $workspace->getWritableProjects();

// Get edge metadata between specific entities
$accessInfo = $workspace->getProjectAccess($project);
echo $accessInfo['access_level']; // 'write'
echo $accessInfo['added_by']; // user ID
echo $accessInfo['is_primary']; // true

// Update relationship metadata
$workspace->updateProjectAccess($project, [
    'access_level' => 'read',
    'downgraded_reason' => 'Project completed',
    'downgraded_by' => $adminUser->getId()
]);

// Complex queries on relationships
$webProjects = $workspace->getProjectsByType('web');
$primaryProjects = $workspace->getPrimaryProjects();
```

## Benefits of This Approach

1. **Clean Entities**: Domain logic stays in entities, relationships are managed separately
2. **Rich Edge Data**: Full access to relationship metadata
3. **Flexible Querying**: Query relationships by any metadata property
4. **Performance**: Can optimize relationship loading as needed
5. **Testable**: Easy to mock RelationshipManager for testing
6. **Vector DB Ready**: Metadata can include embeddings, similarities, etc.

This approach gives you the best of both worlds - clean domain entities with powerful relationship management and rich edge metadata.
