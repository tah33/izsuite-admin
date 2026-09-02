{{--
    Shared branded header row for transactional email.

    Uses absolute asset() URLs and a PNG lockup: mail clients do not render SVG
    reliably, and relative paths do not resolve outside the app. The reversed
    lockup sits on the brand navy plate; `alt` carries the name for clients that
    block images.
--}}
@php
    $brandName = setting('site_name', config('brand.name'));
    $logoPath  = setting('site_logo');
    $logoUrl   = $logoPath
        ? asset('storage/'.ltrim($logoPath, '/'))
        : asset(config('brand.assets.logo_light_png'));
@endphp
<tr>
    <td style="background-color:{{ config('brand.colors.ink') }}; padding:32px 40px; text-align:center;">
        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" width="180"
             style="display:inline-block; width:180px; max-width:70%; height:auto; border:0; outline:none; text-decoration:none;">
    </td>
</tr>
