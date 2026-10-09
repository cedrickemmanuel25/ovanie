{{-- Photos (publiques) + documents (privés). Variables : $photos, $docs, $photosLabel, $photosRequired. --}}
<div class="op-media" data-op-media>
    <div class="op-media__photos @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
        <p class="op-media__label">{{ $photosLabel }} @if($photosRequired)<em>*</em>@endif</p>
        <div class="op-thumbs" data-op-thumbs>
            <label class="op-drop">
                <input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple data-op-photos>
                @proicon('upload', 'oa-ico')
                <span>Glissez-déposez vos images ou cliquez pour parcourir</span>
                <small>JPG, PNG, WEBP (max 10 Mo)</small>
            </label>
            @foreach($photos as $photo)
                <figure class="op-thumb" data-existing>
                    <img src="{{ $photo->url }}" alt="">
                    @if($loop->first)<figcaption>Image principale</figcaption>@endif
                    <label class="op-thumb__remove" title="Retirer"><input type="checkbox" name="remove_files[]" value="{{ $photo->id }}">@proicon('x', 'oa-ico')</label>
                </figure>
            @endforeach
        </div>
        @error('photos')<p class="op-error">{{ $message }}</p>@enderror
        @error('photos.*')<p class="op-error">{{ $message }}</p>@enderror
    </div>
    <div class="op-media__docs">
        <p class="op-media__label">Documents complémentaires <small>(facultatif)</small></p>
        <div class="op-doc-list" data-op-doc-list>
            @foreach($docs as $doc)
                <span class="op-doc-pill">@proicon('file', 'oa-ico'){{ \Illuminate\Support\Str::limit($doc->original_name, 18) }}
                    <label title="Retirer"><input type="checkbox" name="remove_files[]" value="{{ $doc->id }}">@proicon('x', 'oa-ico')</label></span>
            @endforeach
        </div>
        <label class="op-add-doc">
            <input type="file" name="documents[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple data-op-docs>
            @proicon('plus', 'oa-ico') Ajouter un document
        </label>
        <small class="op-muted">PDF, DOC, XLS (max 10 Mo)</small>
        @error('documents.*')<p class="op-error">{{ $message }}</p>@enderror
    </div>
</div>
