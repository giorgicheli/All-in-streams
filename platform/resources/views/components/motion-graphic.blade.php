@php $motionId = 'ais-motion-'.Str::uuid(); @endphp
<div class="ais-motion-graphic" data-motion-graphic>
<svg width="0" height="0" style="position:absolute"><defs>
<symbol id="{{ $motionId }}-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></symbol>
<symbol id="{{ $motionId }}-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></symbol>
<symbol id="{{ $motionId }}-heart" viewBox="0 0 24 24"><path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 5.500-7 10-7 10z"/></symbol>
<symbol id="{{ $motionId }}-brief" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5h6v2"/></symbol>
<symbol id="{{ $motionId }}-chart" viewBox="0 0 24 24"><path d="M5 20V11M12 20V4M19 20v-7"/></symbol>
<symbol id="{{ $motionId }}-check" viewBox="0 0 24 24"><path d="M5 12.5l4.500 4.500L19 7.500"/></symbol>
<symbol id="{{ $motionId }}-doc" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h7"/></symbol>
<symbol id="{{ $motionId }}-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.500"/><path d="M2.500 20c0-4 3-6 6.500-6s6.500 2 6.500 6"/><circle cx="17.500" cy="9" r="2.500"/><path d="M17 14c3 0 4.500 2 4.500 5"/></symbol>
<symbol id="{{ $motionId }}-pin" viewBox="0 0 24 24"><path d="M12 21s7-6 7-11a7 7 0 0 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.500"/></symbol>
<symbol id="{{ $motionId }}-bolt" viewBox="0 0 24 24"><path d="M13 3L5 13h6l-1 8 8-10h-6z"/></symbol>
</defs></svg>

<div class="mg-viewport" data-motion-graphic-viewport><div class="mg-stage" data-motion-graphic-stage aria-hidden="true">

<!-- 0–3s -->
<div class="mg-scene mg-on">
<div class="mg-col"><div class="mg-h mg-big mg-a">Build a community around your brand.</div></div>
<div class="mg-col" style="top:310px"><div class="mg-vis" style="margin-top:0">
<svg class="mg-lines" viewBox="0 0 640 440"><path class="mg-ln2 mg-dr" pathLength="1" style="--d:1.2s" d="M290 140 170 180"/><path class="mg-ln2 mg-dr" pathLength="1" style="--d:1.3s" d="M350 140 480 165"/></svg>
<div class="mg-c mg-t mg-cb mg-p" style="left:320px;top:80px;width:260px;height:130px;padding:22px;text-align:left;--d:.3s">
<div style="display:flex;align-items:center;gap:12px;font:800 32px/1 'Inter Variable',Inter,sans-serif"><svg class="mg-ic"><use href="#{{ $motionId }}-brief"/></svg>Campaign</div>
<div style="margin-top:20px;display:grid;gap:8px"><b style="height:9px;border-radius:5px;background:rgba(255,255,255,.7);width:80%"></b><b style="height:9px;border-radius:5px;background:rgba(255,255,255,.45);width:55%"></b></div></div>
<div class="mg-c mg-f" style="left:170px;top:285px;--fx:150px;--fy:-205px;--d:1.1s;z-index:-1"><div class="mg-t mg-live"><div class="mg-th"><span class="mg-pill"><i></i>Live</span><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><div class="mg-bub"><b></b><b></b></div></div><div class="mg-lb">Livestream</div></div></div>
<div class="mg-c mg-f" style="left:480px;top:290px;--fx:-160px;--fy:-210px;--d:1.3s;z-index:-1"><div class="mg-t mg-short"><div class="mg-th"><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><span class="mg-rx"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></span></div><div class="mg-lb">Short-form</div></div></div>
<div class="mg-c mg-chip mg-p" style="left:60px;top:150px;--d:1.9s"><svg class="mg-ic"><use href="#{{ $motionId }}-chat"/></svg>Chat</div>
<div class="mg-c mg-chip mg-p" style="left:585px;top:130px;--d:2.1s"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg>Likes</div>
</div></div>
</div>

<!-- 3–6s brands -->
<div class="mg-scene">
<div class="mg-col"><div class="mg-lab mg-br mg-a"><svg class="mg-ic"><use href="#{{ $motionId }}-brief"/></svg>For brands</div>
<div class="mg-h mg-a" style="--d:.15s">Reach the right audiences. See how your campaign performs.</div>
<div class="mg-vis">
<div class="mg-x" style="--d:1.5s;position:absolute;inset:0">
<svg class="mg-lines" viewBox="0 0 640 440"><path class="mg-ln2 mg-dr" pathLength="1" style="--d:.5s" d="M280 100 130 170"/><path class="mg-ln2 mg-dr" pathLength="1" style="--d:.6s" d="M320 100V185"/><path class="mg-ln2 mg-dr" pathLength="1" style="--d:.7s" d="M360 100 510 170"/></svg>
<div class="mg-c mg-t mg-cb mg-p" style="left:320px;top:55px;width:290px;height:88px;display:flex;align-items:center;justify-content:center;gap:12px;font:800 32px/1 'Inter Variable',Inter,sans-serif"><svg class="mg-ic"><use href="#{{ $motionId }}-brief"/></svg>Your campaign</div>
<div class="mg-c mg-p" style="left:110px;top:240px;scale:.6;--d:.6s"><div class="mg-t mg-short"><div class="mg-th"><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><span class="mg-rx"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></span></div><div class="mg-lb">Short-form</div></div></div>
<div class="mg-c mg-p" style="left:320px;top:240px;scale:.52;--d:.75s"><div class="mg-t mg-live"><div class="mg-th"><span class="mg-pill"><i></i>Live</span><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><div class="mg-bub"><b></b><b></b></div></div><div class="mg-lb">Livestream</div></div></div>
<div class="mg-c mg-p" style="left:530px;top:240px;scale:.6;--d:.9s"><div class="mg-t mg-short"><div class="mg-th"><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><span class="mg-rx"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></span></div><div class="mg-lb">Short-form</div></div></div>
<div class="mg-c mg-chip mg-p" style="left:110px;top:345px;--d:1s"><svg class="mg-ic"><use href="#{{ $motionId }}-pin"/></svg>UK</div>
<div class="mg-c mg-chip mg-p" style="left:320px;top:345px;--d:1.1s"><svg class="mg-ic"><use href="#{{ $motionId }}-pin"/></svg>Germany</div>
<div class="mg-c mg-chip mg-p" style="left:530px;top:345px;--d:1.2s"><svg class="mg-ic"><use href="#{{ $motionId }}-pin"/></svg>Brazil</div>
</div>
<div class="mg-c mg-t mg-rp mg-p" style="left:320px;top:225px;--d:1.8s">
<div style="display:flex;align-items:center;gap:12px;font:800 32px/1 'Inter Variable',Inter,sans-serif;text-align:left"><svg class="mg-ic" style="color:var(--cb)"><use href="#{{ $motionId }}-chart"/></svg>Campaign report</div>
<div class="mg-r"><span>Reach</span><div class="mg-tr"><b class="mg-gr" style="width:78%;--d:2.1s"></b></div></div>
<div class="mg-r"><span>Engagement</span><div class="mg-tr"><b class="mg-gr" style="width:62%;--d:2.3s"></b></div></div>
<div class="mg-r"><span>Content live</span><div class="mg-tr"><b class="mg-gr" style="width:92%;--d:2.5s"></b></div></div>
</div></div></div>
</div>

<!-- 6–9s agencies -->
<div class="mg-scene">
<div class="mg-col"><div class="mg-lab mg-ag mg-a"><svg class="mg-ic"><use href="#{{ $motionId }}-doc"/></svg>For agencies</div>
<div class="mg-h mg-a" style="--d:.15s">Bring the brief. We coordinate the campaign.</div>
<div class="mg-vis">
<div class="mg-c mg-t mg-p" style="left:320px;top:55px;width:340px;height:100px;padding:0 24px;display:flex;align-items:center;gap:16px;--d:.4s;z-index:2;border-color:#F3B97A">
<svg class="mg-ic" style="width:44px;height:44px;color:#C4620A"><use href="#{{ $motionId }}-doc"/></svg>
<div style="text-align:left"><div style="font:800 32px/1 'Inter Variable',Inter,sans-serif">Client brief</div><div style="margin-top:10px;height:9px;width:150px;border-radius:5px;background:var(--line)"></div></div></div>
<div class="mg-c mg-t mg-row mg-f" style="left:320px;top:190px;--fx:0px;--fy:-120px;--d:1.1s"><span class="mg-ib"><svg class="mg-ic"><use href="#{{ $motionId }}-play"/></svg></span>Livestream integration<span class="mg-ok mg-p" style="--d:2.2s"><svg class="mg-ic"><use href="#{{ $motionId }}-check"/></svg></span></div>
<div class="mg-c mg-t mg-row mg-f" style="left:320px;top:272px;--fx:0px;--fy:-200px;--d:1.35s"><span class="mg-ib"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></span>Short-form video<span class="mg-ok mg-p" style="--d:2.45s"><svg class="mg-ic"><use href="#{{ $motionId }}-check"/></svg></span></div>
<div class="mg-c mg-t mg-row mg-f" style="left:320px;top:354px;--fx:0px;--fy:-280px;--d:1.6s"><span class="mg-ib"><svg class="mg-ic"><use href="#{{ $motionId }}-chart"/></svg></span>Campaign report<span class="mg-ok mg-p" style="--d:2.7s"><svg class="mg-ic"><use href="#{{ $motionId }}-check"/></svg></span></div>
</div></div>
</div>

<!-- 9–12s creators -->
<div class="mg-scene">
<div class="mg-col"><div class="mg-lab mg-cr mg-a"><svg class="mg-ic"><use href="#{{ $motionId }}-users"/></svg>For streamers &amp; influencers</div>
<div class="mg-h mg-a" style="--d:.15s">Get paid for campaigns that fit your content and audience.</div>
<div class="mg-vis" style="margin-top:44px">
<div class="mg-panel mg-p" style="--d:.2s"></div>
<div class="mg-c mg-t mg-cb mg-offer mg-p" style="left:320px;top:125px;--d:.45s">
<div class="mg-top"><span class="mg-ib"><svg class="mg-ic"><use href="#{{ $motionId }}-brief"/></svg></span>Paid campaign offer</div>
<div class="mg-pills"><span class="mg-chip"><svg class="mg-ic"><use href="#{{ $motionId }}-check"/></svg>Paid</span><span class="mg-chip"><svg class="mg-ic"><use href="#{{ $motionId }}-users"/></svg>Fits your audience</span></div>
<div class="mg-btn"><span>Accept offer</span><span class="mg-sb mg-p" style="--d:2s;background:#fff;border-radius:14px"><svg class="mg-ic" style="width:22px;height:22px;vertical-align:-4px;stroke-width:3"><use href="#{{ $motionId }}-check"/></svg> Accepted</span></div>
</div>
<div class="mg-c mg-p" style="left:185px;top:345px;scale:.72;--d:1.1s"><div style="position:relative"><span class="mg-coin">$</span><div class="mg-t mg-live"><div class="mg-th"><span class="mg-pill"><i></i>Live</span><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><div class="mg-bub"><b></b><b></b></div></div><div class="mg-lb">Livestream</div></div></div></div>
<div class="mg-c mg-p" style="left:470px;top:345px;scale:.64;--d:1.35s"><div style="position:relative"><span class="mg-coin">$</span><div class="mg-t mg-short"><div class="mg-th"><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><span class="mg-rx"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></span></div><div class="mg-lb">Short-form</div></div></div></div>
</div></div>
</div>

<!-- 12–15s -->
<div class="mg-scene">
<div class="mg-col"><div class="mg-word mg-a">All In <em>Streams</em></div>
<div class="mg-vis" style="height:340px;margin-top:34px">
<svg class="mg-lines" style="height:340px" viewBox="0 0 640 340"><path class="mg-ln2 mg-dr" pathLength="1" style="--d:.9s" d="M195 90 260 140"/><path class="mg-ln2 mg-dr" pathLength="1" style="--d:1s" d="M200 245 260 205"/><path class="mg-ln2 mg-dr" pathLength="1" style="--d:1.1s" d="M475 170H395"/></svg>
<div class="mg-c mg-pulse" style="left:320px;top:170px;--d:1s"></div>
<div class="mg-c mg-ring mg-p" style="left:320px;top:170px;--d:.3s"><svg class="mg-ic"><use href="#{{ $motionId }}-users"/></svg></div>
<div class="mg-c mg-t mg-cb mg-f" style="left:115px;top:62px;width:210px;height:84px;display:flex;align-items:center;justify-content:center;gap:10px;font:800 26px/1 'Inter Variable',Inter,sans-serif;--fx:205px;--fy:108px;--d:.4s"><svg class="mg-ic"><use href="#{{ $motionId }}-brief"/></svg>Campaign</div>
<div class="mg-c mg-f" style="left:125px;top:260px;scale:.6;--fx:195px;--fy:-90px;--d:.55s"><div class="mg-t mg-live"><div class="mg-th"><span class="mg-pill"><i></i>Live</span><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><div class="mg-bub"><b></b><b></b></div></div><div class="mg-lb">Livestream</div></div></div>
<div class="mg-c mg-f" style="left:530px;top:170px;scale:.68;--fx:-210px;--fy:0px;--d:.7s"><div class="mg-t mg-short"><div class="mg-th"><svg class="mg-ic mg-pl"><use href="#{{ $motionId }}-play"/></svg><span class="mg-rx"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></span></div><div class="mg-lb">Short-form</div></div></div>
<div class="mg-c mg-rt" style="left:320px;top:170px;--x:-170px;--y:-75px;--d:1.2s"><svg class="mg-ic"><use href="#{{ $motionId }}-chat"/></svg></div>
<div class="mg-c mg-rt" style="left:320px;top:170px;--x:-175px;--y:80px;--d:1.7s"><svg class="mg-ic"><use href="#{{ $motionId }}-heart"/></svg></div>
<div class="mg-c mg-rt" style="left:320px;top:170px;--x:180px;--y:-70px;--d:1.45s"><svg class="mg-ic"><use href="#{{ $motionId }}-bolt"/></svg></div>
<div class="mg-c mg-rt" style="left:320px;top:170px;--x:190px;--y:70px;--d:1.95s"><svg class="mg-ic"><use href="#{{ $motionId }}-chat"/></svg></div>
<div class="mg-c" style="left:320px;top:290px;font:700 22px/1 'Inter Variable',Inter,sans-serif;color:var(--mut)">Shared audience</div>
</div>
<div class="mg-cta mg-a" style="--d:.8s"><div class="mg-p1">Start a campaign</div><div class="mg-p2">Join the creator network</div></div>
</div>
</div>

<div class="mg-bar" data-motion-graphic-progress></div>

</div></div>
</div>
