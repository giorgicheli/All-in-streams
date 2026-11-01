<section class="section line-top"><div class="container faq-wrap"><div><span class="eyebrow">{{ $data['eyebrow'] }}</span><h2>{{ $data['title_line_1'] }}<br>{{ $data['title_line_2'] }}</h2></div>
    <div class="faq-list">@foreach($data['items'] as $item)<details><summary>{{ $item['question'] }}<x-ui-icon name="plus"/></summary><p class="content-lines">{{ $item['answer'] }}</p></details>@endforeach</div>
</div></section>
