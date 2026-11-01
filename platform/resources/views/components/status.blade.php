@props(['value'])
@php $labels=['submitted'=>'Admin review','review'=>'Admin review','matching'=>'Matching','proposal'=>'Proposal ready','contracts'=>'Contracts','live'=>'Live','completed'=>'Completed','declined'=>'Declined','changes_requested'=>'Changes requested','pending'=>'Awaiting response','interested'=>'Interested','uploaded'=>'Uploaded','signed'=>'Signed','approved'=>'Approved']; @endphp
<span {{ $attributes->class(['status','status-'.$value]) }}><span></span>{{ $labels[$value] ?? ucfirst(str_replace('_',' ',$value)) }}</span>
