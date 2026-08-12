@foreach ($categories as $category)
    @php $isActive = in_array($category->id, $activeCategories); @endphp
    <button
        type="button"
        wire:click="toggleCategory({{ $category->id }})"
        class="em-cat {{ $isActive ? 'em-cat--active' : '' }}"
        style="--cat-color: {{ $category->color }}"
        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
    >
        <x-em.category-icon
            :slug="$category->slug"
            :color="$isActive ? null : $category->color"
            :size="16"
        />
        {{ $category->name }}
    </button>
@endforeach
