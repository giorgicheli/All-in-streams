<x-portal-layout title="Talent roster">
    <div class="page-heading"><div><span class="eyebrow">THE TALENT YOU REPRESENT</span><h1>Your agency roster</h1><p>Roster talent belongs to your agency. Independent creator accounts remain separate.</p></div></div>
    @if($errors->any())<div class="alert error"><span>{{ $errors->first() }}</span></div>@endif
    <section class="panel mb-6"><div class="panel-header"><h2><x-ui-icon name="users"/> Your roster</h2><span class="tag">{{ $talents->count() }} talent</span></div>
        @forelse($talents as $talent)<div class="document-card" style="flex-wrap:wrap"><span class="avatar"><x-ui-icon name="user"/></span><div><h3>{{ $talent->public_name }}</h3><p>{{ $talent->country }} · {{ $talent->platform }} · {{ number_format($talent->followers) }} followers</p><x-status :value="$talent->status"/></div><a href="{{ route('agency.roster',['edit'=>$talent->id]) }}" class="btn btn-secondary btn-small">Edit</a><form method="POST" action="{{ route('agency.talent.destroy',$talent) }}" onsubmit="return confirm('Remove this talent from your active roster? Campaign history will be retained.');">@csrf @method('DELETE')<button class="btn btn-secondary btn-small">Remove</button></form></div>@empty<div class="empty-state"><x-ui-icon name="users"/><h3>Build your reviewed roster.</h3><p>Add the creators you represent below. Staff approval is required before campaign nomination.</p></div>@endforelse
    </section>
    <form method="POST" action="{{ $editing?route('agency.talent.update',$editing):route('agency.talent.store') }}" class="panel wizard-panel">@csrf @if($editing)@method('PATCH')@endif
        <h2 class="wizard-section-title">{{ $editing?'Update roster talent':'Add roster talent' }}</h2><p class="wizard-description">A verified channel and representative audience figures help the team review this talent.</p>
        @include('agency.partials.talent-fields',['talent'=>$editing])
        <div class="privacy-note"><x-ui-icon name="shield"/><span>New or changed talent is submitted for staff review. Talent committed to an active contract or live campaign cannot be changed or removed.</span></div>
        <div class="wizard-actions"><button class="btn btn-primary">{{ $editing?'Save and request review':'Add and request review' }}</button>@if($editing)<a href="{{ route('agency.roster') }}" class="btn btn-secondary">Cancel editing</a>@endif</div>
    </form>
</x-portal-layout>
