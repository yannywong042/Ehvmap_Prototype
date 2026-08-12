// Client-side photo normalisation for the submit form.
//
// Every photo a visitor picks is converted to JPEG and re-encoded until it
// fits the size budget below, so multi-megabyte phone photos never hit the
// server's upload limits. The compression guarantees the budget rather than
// aiming at it: encoder output size cannot be predicted from dimensions, so
// each pass measures the actual blob and steps quality down first, then
// dimensions, until the result fits. The server-side `max:` rule is purely
// an abuse guard for uploads that bypass this pipeline.

// The budget sits just under the server's `max:4096` (KB) rule, so a photo
// keeps as much of that allowance as possible while leaving headroom for the
// multipart overhead of the upload itself.
const SIZE_BUDGET_BYTES = 3.5 * 1024 * 1024;
const MAX_LONGEST_EDGE = 3000;
const QUALITY_STEPS = [0.92, 0.85, 0.78, 0.7, 0.6];
// A photo that is still over budget at quality 0.6 gets its dimensions
// halved and the quality ladder re-run. Real photos never need more than
// one halving; the cap only guards against a runaway loop.
const MAX_DIMENSION_HALVINGS = 8;

const decodeImage = async (file) => {
    // createImageBitmap applies the EXIF orientation flag during decode in
    // all current browsers, so portrait phone photos keep their rotation.
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file);
        } catch {
            // Fall through: a browser may refuse a format here (e.g. HEIC)
            // that it can still decode through an <img> element.
        }
    }

    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error(`Cannot decode ${file.type || 'file'} as an image`));
        };

        image.src = url;
    });
};

const drawToCanvas = (source, width, height) => {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    // A canvas starts fully transparent and JPEG has no alpha channel, so an
    // unfilled canvas would turn transparent PNG pixels black.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(source, 0, 0, width, height);

    return canvas;
};

const encodeJpeg = (canvas, quality) =>
    new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                // toBlob silently falls back to PNG when it cannot encode the
                // requested type; treat that as a failure rather than looping
                // over ever-larger PNGs.
                if (blob && blob.type === 'image/jpeg') {
                    resolve(blob);
                } else {
                    reject(new Error('JPEG encoding is unavailable'));
                }
            },
            'image/jpeg',
            quality,
        );
    });

const jpegFileName = (originalName) => {
    const base = (originalName || '').replace(/\.[^.]*$/, '').trim();

    return `${base || 'photo'}.jpg`;
};

export const compressPhotoToJpeg = async (file) => {
    const source = await decodeImage(file);

    try {
        const sourceWidth = source.naturalWidth || source.width;
        const sourceHeight = source.naturalHeight || source.height;

        // An already-JPEG photo under budget and already within the dimension
        // cap is uploaded untouched — re-encoding it would only stack
        // generational quality loss on a photo that was already compliant.
        if (
            file.type === 'image/jpeg' &&
            file.size <= SIZE_BUDGET_BYTES &&
            Math.max(sourceWidth, sourceHeight) <= MAX_LONGEST_EDGE
        ) {
            return file;
        }

        // Cap the longest edge, but never upscale a smaller image.
        const scale = Math.min(1, MAX_LONGEST_EDGE / Math.max(sourceWidth, sourceHeight));
        let width = Math.max(1, Math.round(sourceWidth * scale));
        let height = Math.max(1, Math.round(sourceHeight * scale));
        let blob = null;

        for (let halvings = 0; halvings <= MAX_DIMENSION_HALVINGS; halvings++) {
            const canvas = drawToCanvas(source, width, height);

            for (const quality of QUALITY_STEPS) {
                blob = await encodeJpeg(canvas, quality);

                if (blob.size <= SIZE_BUDGET_BYTES) {
                    return new File([blob], jpegFileName(file.name), { type: 'image/jpeg' });
                }
            }

            width = Math.max(1, Math.round(width / 2));
            height = Math.max(1, Math.round(height / 2));
        }

        // Unreachable for any real image; hand the last attempt to the
        // server-side rule rather than blocking the submission here.
        return new File([blob], jpegFileName(file.name), { type: 'image/jpeg' });
    } finally {
        source.close?.();
    }
};

window.submitExperienceForm = () => ({
    preview: null,
    processing: false,
    showInput: true,
    // Bumped whenever the photo is removed or replaced, so a compression or
    // upload that is still in flight for an older pick can detect it has
    // been superseded and stop without touching the newer state.
    photoToken: 0,

    async photoSelected(file) {
        if (!file) {
            return;
        }

        const token = ++this.photoToken;
        this.processing = true;
        this.previewFile(file, token);

        let upload = file;

        try {
            upload = await compressPhotoToJpeg(file);
        } catch {
            // Undecodable pick (HEIC on some browsers, or not an image at
            // all): upload the original unmodified and let the server-side
            // validation decide. A failed compression must never block a
            // submission the server would otherwise accept.
        }

        if (token !== this.photoToken) {
            return;
        }

        // Abort any still-running upload of an earlier pick before starting
        // this one, so the replacement never races the original.
        this.$wire.cancelUpload('photo');
        this.$wire.upload(
            'photo',
            upload,
            () => this.uploadSettled(token),
            () => this.uploadSettled(token),
            () => {},
            // cancelUpload() invokes this stored callback, so it must exist
            // even though settling is already handled by the token bump.
            () => this.uploadSettled(token),
        );
    },

    uploadSettled(token) {
        if (token === this.photoToken) {
            this.processing = false;
        }
    },

    previewFile(file, token) {
        const reader = new FileReader();

        reader.onload = (event) => {
            // Readers of different files can finish out of order, so only
            // the read for the current pick may set the preview.
            if (token === this.photoToken) {
                this.preview = event.target.result;
            }
        };

        reader.readAsDataURL(file);
    },

    removePhoto() {
        this.photoToken++;
        this.processing = false;
        this.preview = null;
        // Abort any in-flight upload FIRST: once its XHR completes, Livewire's
        // _finishUpload would re-set the photo property server-side and the
        // submission would silently include the photo the user just removed.
        this.$wire.cancelUpload('photo');
        // Re-mount the file input so picking the same file again re-fires
        // the change event.
        this.showInput = false;
        this.$wire.removePhoto();
        this.$nextTick(() => {
            this.showInput = true;
        });
    },
});
