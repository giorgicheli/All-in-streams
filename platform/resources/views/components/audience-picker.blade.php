@props(['countries' => [], 'selected' => []])
<details class="audience-picker" data-audience-picker>
    <summary aria-label="Choose audience countries"><span data-audience-selection>Select audience countries</span><x-ui-icon name="chevron" size="18"/></summary>
    <div class="audience-picker-panel">
        <label class="sr-only" for="audience-country-search">Find a country</label>
        <input id="audience-country-search" type="search" placeholder="Find a country…" autocomplete="off" data-audience-search>
        <div class="audience-country-options">
            @foreach($countries as $country)
                <label data-audience-option><input type="checkbox" name="audience_geos[]" value="{{ $country }}" @checked(in_array($country, $selected, true))><span>{{ $country }}</span><x-ui-icon name="check" size="15"/></label>
            @endforeach
        </div>
        <p class="enquiry-help" data-audience-count>{{ count($selected) }} selected</p>
    </div>
</details>
