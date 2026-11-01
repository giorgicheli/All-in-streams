@props(['name' => 'arrow', 'size' => 20])
@php
$paths = [
 'arrow'=>'M7 17 17 7M7 7h10v10', 'right'=>'M5 12h14m-6-6 6 6-6 6', 'left'=>'M19 12H5m6-6-6 6 6 6',
 'grid'=>'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
 'briefcase'=>'M8 6V4h8v2M3 7h18v13H3zM3 12c5 3 13 3 18 0M10 13h4',
 'file'=>'M14 2H5v20h14V7zM14 2v6h5M8 12h8M8 16h6',
 'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 'user'=>'M20 21v-2a7 7 0 0 0-14 0v2M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 'shield'=>'M12 3 3 7v5c0 6 9 10 9 10s9-4 9-10V7zM8 12l3 3 5-6',
 'check'=>'m5 12 4 4L19 6', 'plus'=>'M12 5v14M5 12h14', 'close'=>'m6 6 12 12M6 18 18 6',
 'globe'=>'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18',
 'game'=>'M7 7h10c4 0 5 10 3 11-2 1-4-3-5-3H9c-1 0-3 4-5 3C2 17 3 7 7 7M6 10v5M3.5 12.5h5M16 11h.01M19 14h.01',
 'trophy'=>'M8 3h8v8a4 4 0 0 1-8 0zM8 5H4v3a4 4 0 0 0 4 4M16 5h4v3a4 4 0 0 1-4 4M12 15v6M8 21h8',
 'target'=>'M21 12a9 9 0 1 1-9-9M17 12a5 5 0 1 1-5-5M12 12l9-9M16 3h5v5',
 'layers'=>'m12 3 10 5-10 5L2 8zM2 12l10 5 10-5M2 16l10 5 10-5',
 'clock'=>'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0M12 7v5l3 2',
 'message'=>'M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-.8L3 21l1.8-5A9 9 0 1 1 21 11M8 11h.01M12 11h.01M16 11h.01',
 'chart'=>'M3 3v18h18M7 14l4-4 4 3 6-8', 'wallet'=>'M20 7H4V4h14v3M3 7h18v14H3zM21 12h-6v5h6',
 'star'=>'m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z',
 'logout'=>'M9 3H3v18h6M10 12h11m-4-4 4 4-4 4', 'menu'=>'M4 6h16M4 12h16M4 18h16',
 'lock'=>'M6 10V7a6 6 0 0 1 12 0v3M4 10h16v12H4zM12 14v4',
 'chevron'=>'m6 9 6 6 6-6', 'download'=>'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
 'spark'=>'m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z',
 'mail'=>'M3 5h18v14H3zM3 5l9 8 9-8', 'eye'=>'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
 'calendar'=>'M4 5h16v16H4zM8 2v6M16 2v6M4 10h16',
];
@endphp
<svg {{ $attributes->class(['icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['arrow'] }}"/></svg>
