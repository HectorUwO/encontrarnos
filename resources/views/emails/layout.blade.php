<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>@yield('title') · {{ config('app.name') }}</title>
</head>
@php
    $logoPath = resource_path('images/email-logo.png');
    // En un correo real el logo viaja incrustado; en la vista previa, como data URI.
    $logo = isset($message)
        ? $message->embed($logoPath)
        : 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
@endphp
<body style="margin:0;padding:0;background-color:#0a0a0a;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        @yield('preheader')
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#111111;border:1px solid #2a2a2a;border-top:6px solid #d0b371;">
                    <tr>
                        <td style="padding:32px 36px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:10px;">
                                        <img src="{{ $logo }}" width="38" height="38" alt="" style="display:block;border:0;">
                                    </td>
                                    <td style="vertical-align:middle;font-family:Manrope,'Segoe UI',Arial,sans-serif;font-size:22px;font-weight:800;color:#f0ecdf;letter-spacing:0.01em;">
                                        encontrarnos<span style="color:#d0b371;">.</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 36px 0;font-family:Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.08em;color:#abb994;">
                            + / @yield('kicker')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 36px 0;font-family:'Barlow Condensed','Arial Narrow',Impact,Arial,sans-serif;font-size:52px;line-height:0.98;font-weight:800;text-transform:uppercase;color:#f0ecdf;">
                            @yield('headline')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 36px 0;font-family:Arial,sans-serif;font-size:16px;line-height:1.7;color:#c9cfc0;">
                            @unless (isset($noGreeting))
                                {{ $name ? 'Hola, '.$name.'.' : 'Hola.' }}
                            @endunless
                            @yield('intro')
                        </td>
                    </tr>
                    @if (! empty($url))
                        <tr>
                            <td style="padding:30px 36px 0;">
                                <table role="presentation" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td bgcolor="#f0ecdf" style="background-color:#f0ecdf;">
                                            <a href="{{ $url }}" style="display:inline-block;padding:17px 30px;font-family:Arial,sans-serif;font-size:14px;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;text-decoration:none;color:#0a0a0a;">
                                                @yield('button') &nbsp;↗
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif
                    @if (! empty($expire) || View::hasSection('disclaimer'))
                        <tr>
                            <td style="padding:28px 36px 0;font-family:Arial,sans-serif;font-size:13px;line-height:1.6;color:#8a9087;">
                                @if (! empty($expire))
                                    El enlace vence en {{ $expire }} minutos.
                                @endif
                                @yield('disclaimer')
                            </td>
                        </tr>
                    @endif
                    @if (! empty($url))
                        <tr>
                            <td style="padding:24px 36px 0;">
                                <div style="border-top:1px solid #2a2a2a;font-size:0;line-height:0;">&nbsp;</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:16px 36px 0;font-family:Arial,sans-serif;font-size:12px;line-height:1.6;color:#6f756c;">
                                ¿El botón no funciona? Copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $url }}" style="color:#d0b371;word-break:break-all;">{{ $url }}</a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:28px 36px 32px;font-family:Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.08em;color:#d0b371;">
                            POR QUIENES FALTAN. CON QUIENES BUSCAN.
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                    <tr>
                        <td align="center" style="padding:20px 0 0;font-family:Arial,sans-serif;font-size:11px;color:#5c625a;">
                            © {{ date('Y') }} {{ config('app.name') }} · México
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
