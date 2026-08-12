<?php

use App\Models\Category;
use App\Models\Experience;
use App\Models\Neighbourhood;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    /** @var list<int> */
    public array $activeCategories = [];

    public string $neighborhood = '';

    #[Url(as: 'wijk', history: true)]
    public ?string $selectedNeighbourhood = null;

    public bool $neighbourhoodsUnavailable = false;

    public function mount(): void
    {
        if ($this->selectedNeighbourhood !== null && $this->selectedNeighbourhoodModel() === null) {
            // The URL references an unknown wijk slug — reset rather than silently filtering to nothing.
            $this->selectedNeighbourhood = null;
        }
    }

    #[Computed]
    public function experiences(): Collection
    {
        $neighbourhood = $this->selectedNeighbourhoodModel();

        return Experience::published()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('category')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhere('neighborhood', 'like', "%{$this->search}%");
            }))
            ->when($this->activeCategories, fn ($q) => $q->whereIn('category_id', $this->activeCategories))
            ->when($this->neighborhood, fn ($q) => $q->where('neighborhood', $this->neighborhood))
            ->when($neighbourhood, fn ($q) => $q->where('neighbourhood_id', $neighbourhood->id))
            ->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::all();
    }

    /**
     * @return Collection<int, Neighbourhood>
     */
    #[Computed]
    public function neighbourhoods(): Collection
    {
        try {
            $neighbourhoods = Neighbourhood::query()->orderBy('wijknaam')->get();
            $this->neighbourhoodsUnavailable = false;

            return $neighbourhoods;
        } catch (Throwable) {
            $this->neighbourhoodsUnavailable = true;

            return collect();
        }
    }

    public function selectedNeighbourhoodModel(): ?Neighbourhood
    {
        if (! $this->selectedNeighbourhood) {
            return null;
        }

        return $this->neighbourhoods->first(fn (Neighbourhood $n) => $n->slug() === $this->selectedNeighbourhood);
    }

    public function updatedSearch(): void
    {
        $this->dispatchExperiencesUpdated();
    }

    public function updatedNeighborhood(): void
    {
        $this->dispatchExperiencesUpdated();
    }

    public function selectNeighbourhood(?string $slug): void
    {
        $this->selectedNeighbourhood = $slug ?: null;

        $this->dispatchExperiencesUpdated();
        $this->dispatchNeighbourhoodSelected();
    }

    public function toggleCategory(int $categoryId): void
    {
        if (in_array($categoryId, $this->activeCategories, true)) {
            $this->activeCategories = array_values(array_filter($this->activeCategories, fn ($c) => $c !== $categoryId));
        } else {
            $this->activeCategories[] = $categoryId;
        }

        $this->dispatchExperiencesUpdated();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->activeCategories = [];
        $this->neighborhood = '';
        $this->selectedNeighbourhood = null;

        $this->dispatchExperiencesUpdated();
        $this->dispatchNeighbourhoodSelected();
    }

    private function dispatchExperiencesUpdated(): void
    {
        $this->dispatch('experiences-updated', experiences: $this->experiencesForMap());
    }

    private function dispatchNeighbourhoodSelected(): void
    {
        $neighbourhood = $this->selectedNeighbourhoodModel();

        $this->dispatch('neighbourhood-selected', neighbourhood: $neighbourhood?->toArray());
    }

    public function experiencesForMap(): array
    {
        return $this->experiences
            ->map(fn (Experience $experience) => [
                ...$experience->toArray(),
                'date' => $experience->created_at?->format('j M'),
            ])
            ->all();
    }
};
