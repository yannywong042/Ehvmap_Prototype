<?php

use App\Models\Category;
use App\Models\Experience;
use App\Services\WijkLookup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string|max:1000')]
    public string $description = '';

    public string $category = '';

    public string $categorySuggestion = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    /**
     * The browser converts and compresses every photo to a <= 2 MB JPEG
     * before uploading, so the size limit is only an abuse guard for
     * uploads that bypass the JavaScript pipeline. The dimensions rule
     * forces a real image decode: the image rule alone trusts the
     * client-supplied file extension for temporary Livewire uploads, which
     * would let any file renamed to .jpg through. The dimension caps stop
     * decompression bombs — a small, highly-compressible file with an
     * enormous pixel count would otherwise be decoded by every visitor's
     * browser when the photo is shown on the map.
     */
    #[Validate('nullable|image|max:4096|dimensions:min_width=1,min_height=1,max_width=10000,max_height=10000')]
    public $photo = null;

    #[Validate('nullable|email|max:255')]
    public string $authorEmail = '';

    public bool $wantsEvents = false;

    #[Validate('accepted')]
    public bool $acceptedTerms = false;

    public bool $submitted = false;

    #[Computed]
    public function categories(): Collection
    {
        return Category::all();
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    protected function rules(): array
    {
        return [
            'category' => ['required', Rule::in([
                'other',
                ...Category::pluck('id')->map(fn (int $id): string => (string) $id),
            ])],
            'categorySuggestion' => ['required_if:category,other', 'nullable', 'string', 'max:255'],
            // Constrain the location to the Eindhoven municipality bounding box so
            // a pin can only ever be dropped inside the city.
            'latitude' => ['required', 'numeric', 'between:51.38,51.51'],
            'longitude' => ['required', 'numeric', 'between:5.38,5.57'],
        ];
    }

    public function removePhoto(): void
    {
        $this->reset('photo');
        $this->resetErrorBag('photo');
    }

    /**
     * Collapse the independent latitude/longitude failures into a single,
     * translated "location" error so the map picker (one visible control)
     * never reports two errors for coordinates the visitor never sees.
     */
    protected function collapseLocationErrors(Validator $validator): void
    {
        $failed = $validator->failed();

        if (! isset($failed['latitude']) && ! isset($failed['longitude'])) {
            return;
        }

        $isMissing = isset($failed['latitude']['Required']) || isset($failed['longitude']['Required']);

        $validator->errors()->add('location', $isMissing
            ? __('submit.location_required')
            : __('submit.location_out_of_bounds'));

        $validator->errors()->forget('latitude');
        $validator->errors()->forget('longitude');
    }

    public function submit(WijkLookup $wijkLookup): void
    {
        $this->withValidator(fn (Validator $validator) => $validator->after(
            fn (Validator $validator) => $this->collapseLocationErrors($validator)
        ));

        $this->validate();

        $photoPath = $this->photo?->store('experiences', 'public');

        $isOther = $this->category === 'other';

        $neighborhood = $wijkLookup->find((float) $this->latitude, (float) $this->longitude);

        Experience::create([
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $isOther ? null : (int) $this->category,
            'category_suggestion' => $isOther ? $this->categorySuggestion : null,
            'neighborhood' => $neighborhood,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'photo_path' => $photoPath,
            'author_email' => $this->authorEmail ?: null,
            'wants_events' => $this->wantsEvents,
            'status' => 'pending',
            'terms_accepted_at' => now(),
        ]);

        $this->submitted = true;
    }
};
