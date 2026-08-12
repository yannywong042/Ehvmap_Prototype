@props(['experience', 'variant' => 'card'])
@php
    $category = $experience->category;
    $interactive = $variant !== 'popup';
    $isTranslated = $experience->isTranslatedForLocale(app()->getLocale());
    $sourceFlag = ['en' => '🇬🇧', 'nl' => '🇳🇱'][$experience->source_locale] ?? '';
    $limitedLocalizedDescription = Str::limit($experience->localized_description, 120);
    $limitedOriginalDescription = Str::limit($experience->original_description, 120);
@endphp
<article
    @if ($isTranslated) x-data="{ showOriginal: false }" @endif
    {{ $attributes->merge(['class' => 'em-exp' . ($variant === 'popup' ? ' em-exp--popup' : '') . ($interactive ? ' em-exp--interactive' : '')]) }}
>
    <div
        class="em-exp__media{{ $experience->photo_url ? '' : ' em-exp__media--empty' }}"
        @if (! $experience->photo_url && $category)
            style="--cat-color: {{ $category->color }}"
        @endif
    >
        @if ($experience->photo_url)
            <img src="{{ $experience->photo_url }}" alt="{{ $experience->title }}" loading="lazy">
        @endif
        @if ($category)
            <span class="em-exp__cat">
                <x-em.category-icon :slug="$category->slug" :color="$category->color" :size="14" />
                {{ $category->name }}
            </span>
        @endif
    </div>
    <div class="em-exp__body">
        @if ($experience->neighborhood)
            <span class="em-exp__loc">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $experience->neighborhood }}
            </span>
        @endif
        {{-- The title is the author's original text in every locale; only the
             description switches between translation and original. --}}
        <h3 class="em-exp__title">{{ $experience->title }}</h3>
        @if ($isTranslated)
            <p class="em-exp__desc" x-text="showOriginal ? @js($limitedOriginalDescription) : @js($limitedLocalizedDescription)">{{ $limitedLocalizedDescription }}</p>
        @else
            <p class="em-exp__desc">{{ $limitedLocalizedDescription }}</p>
        @endif
        <div class="em-exp__foot">
            @if ($isTranslated)
                <button
                    type="button"
                    class="em-exp__flag"
                    @click.stop="showOriginal = ! showOriginal"
                    :aria-pressed="showOriginal ? 'true' : 'false'"
                    aria-label="{{ __('map.translation_toggle_aria') }}"
                >
                    <span x-show="! showOriginal">{{ __('map.translated_show_original') }} {{ $sourceFlag }}</span>
                    <span x-show="showOriginal" x-cloak>{{ __('map.show_translation') }}</span>
                </button>
            @endif
            <span class="em-exp__date">{{ $experience->created_at?->format('j M') }}</span>
        </div>
    </div>
</article>
