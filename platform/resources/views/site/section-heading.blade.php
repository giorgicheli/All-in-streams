@php $sectionHeadingTag = ($headingLevel ?? 2) === 3 ? 'h3' : 'h2'; @endphp
<div class="section-heading"><div>@unless($hideEyebrow ?? false)<span class="eyebrow">{{ $data['eyebrow'] }}</span>@endunless<{{ $sectionHeadingTag }}>{{ $data['title'] }}</{{ $sectionHeadingTag }}></div><p class="content-lines">{{ $data['description'] }}</p></div>
