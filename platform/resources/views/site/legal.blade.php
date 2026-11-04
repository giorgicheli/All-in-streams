<x-layout :title="$page['seo']['title']" :description="$page['seo']['description']">
    <div class="legal-layout">
    @include('site.navigation')
    <main class="legal-page">
        <div class="container">
            <header class="legal-header"><h1>{{ $page['title'] }}</h1></header>
            <article class="legal-content">{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($page['body'])->linkProtocols(['https', 'mailto', 'tel']) }}</article>
        </div>
    </main>
    @include('site.footer')
    </div>
</x-layout>
