<div>
    @if ($submitted)
        <div class="submit__success">
            <div class="submit__success-icon">✅</div>
            <h2>{{ __('submit.success_title') }}</h2>
            <p>{{ __('submit.success_message') }}</p>
            <a href="{{ route('map') }}" class="em-btn em-btn--primary em-btn--lg">
                {{ __('submit.success_cta') }}
            </a>
        </div>
    @else
        <form wire:submit="submit" class="submit__form" x-data="submitExperienceForm()">
            @if ($errors->any())
                <div class="auth-errors" style="margin-bottom:16px">
                    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="em-callout em-callout--info" role="note">
                <svg class="em-callout__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <div class="em-callout__body">
                    <span class="em-callout__title">{{ __('submit.privacy_title') }}</span>
                    <p>{{ __('submit.privacy_public') }}</p>
                    <p>{{ __('submit.privacy_email') }}</p>
                </div>
            </div>

            <div class="em-field">
                <label class="em-field__label" for="sf-title">{{ __('submit.field_title') }} *</label>
                <div class="em-input">
                    <input id="sf-title" type="text" wire:model="title" required>
                </div>
                @error('title') <p class="em-field__error">{{ $message }}</p> @enderror
            </div>

            <div class="em-field">
                <label class="em-field__label" for="sf-description">{{ __('submit.field_description') }} *</label>
                <div class="em-ta-field">
                    <textarea id="sf-description" class="em-textarea" wire:model="description" rows="4" required></textarea>
                </div>
                @error('description') <p class="em-field__error">{{ $message }}</p> @enderror
            </div>

            <div class="em-field">
                <label class="em-field__label" for="sf-category">{{ __('submit.field_category') }} *</label>
                <div class="em-sel">
                    <select id="sf-category" wire:model.live="category" required>
                        <option value="">— {{ __('map.all_categories') }} —</option>
                        @foreach ($this->categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                        <option value="other">{{ __('submit.other_category') }}</option>
                    </select>
                </div>
                @error('category') <p class="em-field__error">{{ $message }}</p> @enderror
            </div>

            @if ($category === 'other')
                <div class="em-field">
                    <label class="em-field__label" for="sf-category-suggestion">{{ __('submit.category_suggestion_label') }} *</label>
                    <div class="em-input">
                        <input id="sf-category-suggestion" type="text" wire:model="categorySuggestion" placeholder="{{ __('submit.category_suggestion_placeholder') }}">
                    </div>
                    @error('categorySuggestion') <p class="em-field__error">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Location picker (Eindhoven-only map + address search) --}}
            <div class="em-field">
                <label class="em-field__label">{{ __('submit.field_location') }} *</label>
                <x-location-picker
                    lat-path="latitude"
                    lng-path="longitude"
                    :search-placeholder="__('submit.location_search_placeholder')"
                    :search-label="__('submit.location_search_btn')"
                    :hint="__('submit.location_hint')"
                    :out-of-bounds-message="__('submit.location_out_of_bounds')"
                    :unavailable-message="__('submit.location_unavailable')"
                    :error="$errors->first('location')"
                    error-id="location-error"
                />
                @error('location') <p class="em-field__error" id="location-error">{{ $message }}</p> @enderror
            </div>

            {{-- Photo upload: converted to JPEG and compressed in the browser before Livewire uploads it --}}
            <div class="em-field">
                <label class="em-field__label" id="sf-photo-label">{{ __('submit.field_photo') }}</label>
                <div
                    class="dropzone"
                    role="button"
                    tabindex="0"
                    aria-labelledby="sf-photo-label"
                    aria-describedby="sf-photo-hint"
                    x-on:click="if (!preview && !processing) { $refs.photoInput?.click() }"
                    x-on:keydown.enter.prevent="if (!preview && !processing) { $refs.photoInput?.click() }"
                    x-on:keydown.space.prevent="if (!preview && !processing) { $refs.photoInput?.click() }"
                    x-on:dragover.prevent="$el.classList.add('is-dragover')"
                    x-on:dragleave.prevent="$el.classList.remove('is-dragover')"
                    x-on:drop.prevent="$el.classList.remove('is-dragover'); photoSelected($event.dataTransfer.files[0])"
                >
                    <template x-if="preview">
                        <div class="dropzone__preview-wrap" @click.stop>
                            <img :src="preview" alt="" class="dropzone__preview">
                            <p class="dropzone__processing" x-show="processing" x-cloak>{{ __('submit.photo_processing') }}</p>
                            <button
                                type="button"
                                class="em-btn em-btn--ghost em-btn--sm"
                                x-on:click.stop.prevent="removePhoto()"
                            >
                                {{ __('submit.remove_photo') }}
                            </button>
                        </div>
                    </template>
                    <template x-if="!preview">
                        <div class="dropzone__empty">
                            <span class="dropzone__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
                                    <circle cx="12" cy="13" r="3"/>
                                </svg>
                            </span>
                            <p class="dropzone__title">{{ __('submit.drop_hint') }}</p>
                            <p class="dropzone__sub" id="sf-photo-hint">{{ __('submit.photo_hint') }}</p>
                        </div>
                    </template>
                    <template x-if="showInput">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/*"
                            class="dropzone__input"
                            x-ref="photoInput"
                            tabindex="-1"
                            aria-hidden="true"
                            x-on:change="photoSelected($event.target.files[0]); $event.target.value = ''"
                            x-on:click.stop
                        >
                    </template>
                </div>
                @error('photo')
                    <p class="em-field__error">{{ $message }}</p>
                    <button
                        type="button"
                        class="em-link-btn"
                        x-on:click="removePhoto()"
                    >
                        {{ __('submit.try_another_photo') }}
                    </button>
                @enderror
            </div>

            <div class="em-field">
                <label class="em-field__label" for="sf-email">{{ __('submit.field_email') }}</label>
                <div class="em-input">
                    <input
                        id="sf-email"
                        type="email"
                        wire:model="authorEmail"
                        autocomplete="email"
                        aria-describedby="sf-email-hint{{ $errors->has('authorEmail') ? ' sf-email-error' : '' }}"
                        @if ($errors->has('authorEmail')) aria-invalid="true" @endif
                    >
                </div>
                <p class="em-field__hint" id="sf-email-hint">{{ __('submit.email_hint') }}</p>
                @error('authorEmail') <p class="em-field__error" id="sf-email-error">{{ $message }}</p> @enderror
            </div>

            <label class="em-check">
                <input type="checkbox" wire:model="wantsEvents">
                <span class="em-check__box"></span>
                <span class="em-check__label">{{ __('submit.field_wants_events') }}</span>
            </label>

            <label class="em-check">
                <input
                    type="checkbox"
                    wire:model="acceptedTerms"
                    aria-invalid="{{ $errors->has('acceptedTerms') ? 'true' : 'false' }}"
                    @error('acceptedTerms') aria-describedby="sf-accepted-terms-error" @enderror
                >
                <span class="em-check__box"></span>
                <span class="em-check__label">
                    {{ __('submit.terms_prefix') }}
                    <a href="{{ route('terms') }}" target="_blank" rel="noopener">{{ __('submit.terms_link_text') }}</a>
                </span>
            </label>
            @error('acceptedTerms') <p class="em-field__error" id="sf-accepted-terms-error">{{ $message }}</p> @enderror

            <div style="margin-top:24px">
                <button type="submit" class="em-btn em-btn--primary em-btn--lg" x-bind:disabled="processing" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit" x-show="!processing">{{ __('submit.submit_btn') }}</span>
                    <span wire:loading wire:target="submit">{{ __('map.loading') }}</span>
                    <span x-show="processing" x-cloak>{{ __('submit.photo_processing') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
