@props(['name'])
<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" {{ $attributes }}>
@switch($name)
    @case('message')<path d="M2 3.5h12v7.5H6.5L3.5 13.5V11H2z"/><path d="M5 6.5h6M5 8.5h4"/>@break
    @case('share')<circle cx="4" cy="8" r="1.8"/><circle cx="12" cy="4" r="1.8"/><circle cx="12" cy="12" r="1.8"/><path d="M5.6 7.2 10.4 4.8M5.6 8.8l4.8 2.4"/>@break
    @case('globe')<circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c1.8 2 1.8 10 0 12M8 2c-1.8 2-1.8 10 0 12"/>@break
    @case('megaphone')<path d="M2.5 6.5v3h2l6 3v-9l-6 3z"/><path d="M4.5 9.5l1 4h1.5l-.6-3.5M12.5 6.5c.8.5.8 2.5 0 3"/>@break
    @case('home')<path d="M2.5 7.5 8 3l5.5 4.5V13H2.5z"/><path d="M6.5 13V9.5h3V13"/>@break
    @case('chart')<path d="M2.5 13.5h11M4 11V8M7 11V5M10 11V7M13 11V3.5"/>@break
    @case('screen')<rect x="2" y="3" width="12" height="8" rx="1"/><path d="M6 13.5h4M8 11v2.5"/>@break
    @case('people')<circle cx="8" cy="5.5" r="2.5"/><path d="M3 14c.6-2.8 2.6-4.2 5-4.2s4.4 1.4 5 4.2"/>@break
    @case('doc')<path d="M4 2h6l3 3v9H4z"/><path d="M6.5 8.5h4M6.5 11h4"/>@break
    @case('clipboard')<path d="M5.5 2.5h5v2h-5z"/><path d="M10.5 3.5h2v10h-9v-10h2"/><path d="M5.8 9l1.6 1.6 3-3.2"/>@break
    @case('swap')<path d="M1.5 5.5h9l-2.5-2.5M14.5 10.5h-9l2.5 2.5"/>@break
    @case('tag')<path d="M8.5 2H14v5.5L7.5 14 2 8.5z"/><circle cx="11" cy="5" r="1"/>@break
    @case('cart')<path d="M1.5 2.5h2l1.5 8h7.5l1.5-5.5H4.3"/><circle cx="6" cy="13.5" r="1"/><circle cx="12" cy="13.5" r="1"/>@break
    @case('box')<rect x="2" y="4" width="12" height="9" rx="1"/><path d="M5 4V2.5h6V4"/>@break
    @case('warehouse')<path d="M1.5 6 8 2.5 14.5 6v7.5h-13z"/><path d="M4.5 13.5v-5h7v5M4.5 10.5h7"/>@break
    @case('stage')<path d="M2 13.5h12M3.5 13.5V9h9v4.5"/><path d="M5 9 4 4M11 9l1-5M8 9V3"/>@break
    @case('desk')<rect x="2" y="7" width="12" height="2" rx="1"/><path d="M4 9v4.5M12 9v4.5M6 7V4h4v3"/>@break
    @case('invoice')<path d="M4 1.5h8v13l-2-1.2-2 1.2-2-1.2-2 1.2z"/><path d="M6 5h4M6 8h4"/>@break
    @case('user')<circle cx="8" cy="5.5" r="2.5"/><path d="M3 14c.6-2.8 2.6-4.2 5-4.2s4.4 1.4 5 4.2"/>@break
    @case('building')<path d="M2.5 14V3.5h7V14M9.5 7h4v7M1.5 14h13"/><path d="M5 6h2M5 8.5h2M5 11h2"/>@break
    @case('percent')<path d="M3.5 12.5l9-9"/><circle cx="4.5" cy="4.5" r="1.5"/><circle cx="11.5" cy="11.5" r="1.5"/>@break
    @case('pin')<path d="M8 14s4.5-4.2 4.5-7.5a4.5 4.5 0 0 0-9 0C3.5 9.8 8 14 8 14z"/><circle cx="8" cy="6.5" r="1.5"/>@break
    @case('upload')<path d="M8 10.5V2.5M5 5.5l3-3 3 3M2.5 10.5v3h11v-3"/>@break
    @case('sliders')<path d="M2 4h7M12 4h2M2 8h2M7 8h7M2 12h9M14 12h0"/><circle cx="10.5" cy="4" r="1.5"/><circle cx="5.5" cy="8" r="1.5"/><circle cx="12.5" cy="12" r="1.5"/>@break
    @case('bell')<path d="M8 2.5a4 4 0 0 0-4 4v2.5L3 11h10l-1-2V6.5a4 4 0 0 0-4-4zM6.5 13a1.5 1.5 0 0 0 3 0"/>@break
    @case('help')<circle cx="8" cy="8" r="6"/><path d="M6.3 6.2a1.8 1.8 0 1 1 2.4 1.7c-.5.2-.7.6-.7 1.1M8 11.2v.1"/>@break
    @case('logout')<path d="M6 2.5H3v11h3M10.5 5l3 3-3 3M13.5 8H6"/>@break
    @case('search')<circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14"/>@break
    @case('moon')<path d="M13.5 10.2A5.5 5.5 0 0 1 5.8 2.5a5.5 5.5 0 1 0 7.7 7.7z"/>@break
    @case('sun')<circle cx="8" cy="8" r="3"/><path d="M8 1.5v1.5M8 13v1.5M1.5 8H3M13 8h1.5M3.4 3.4l1 1M11.6 11.6l1 1M3.4 12.6l1-1M11.6 4.4l1-1"/>@break
    @case('menu')<path d="M2 4h12M2 8h12M2 12h12"/>@break
    @default<circle cx="8" cy="8" r="5"/>
@endswitch
</svg>
