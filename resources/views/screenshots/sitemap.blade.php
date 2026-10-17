<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    />
    <title>
        {{ __('capell-site-discovery::screenshots.' . ($screen === 'xml-sitemap-output' ? 'xml_title' : 'html_title')) }}
    </title>
    <style>
        :root { font-family: ui-sans-serif, system-ui, sans-serif; color: #172c32; background: #f5f7f5; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 64px 32px; }
        main { max-width: 1080px; margin: auto; }
        header { border-bottom: 1px solid #c8d7ce; padding-bottom: 24px; margin-bottom: 48px; color: #3a6551; font-weight: 600; }
        h1 { font-size: 48px; line-height: 1.1; margin: 0 0 20px; }
        p { color: #596c67; line-height: 1.6; }
        ul { padding: 0; list-style: none; display: grid; gap: 20px; margin-top: 36px; }
        a { display: block; border: 1px solid #cad8cf; border-radius: 12px; padding: 24px; background: white; color: #275f47; text-decoration: none; font-size: 24px; }
        small { display: block; font-size: 14px; margin-top: 12px; color: #596c67; }
        pre { background: #172c32; color: #d1eadd; border-radius: 12px; padding: 32px; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8; font-size: 18px; }
    </style>
</head>
<body>
    <main>
        <header>{{ __('capell-site-discovery::screenshots.site') }}</header>
        @if ($screen === 'xml-sitemap-output')
            <section data-screenshot-xml-sitemap>
                <h1>
                    {{ __('capell-site-discovery::screenshots.xml_title') }}
                </h1>
                <p>{{ __('capell-site-discovery::screenshots.xml_description') }}</p>
                <pre>{{ $fixture->xml }}</pre>
            </section>
        @else
            <section data-screenshot-html-sitemap>
                <h1>
                    {{ __('capell-site-discovery::screenshots.html_title') }}
                </h1>
                <p>{{ __('capell-site-discovery::screenshots.html_description') }}</p>
                <ul>
                    @foreach ($fixture->entries as $entry)
                        <li>
                            <a href="{{ $entry->canonicalUrl }}"
                                >{{ __('capell-site-discovery::screenshots.' . match (parse_url($entry->canonicalUrl, PHP_URL_PATH)) { '/workshops' => 'workshops', '/visit' => 'visit', default => 'home' }) }}<small
                                    >{{ $entry->canonicalUrl }}</small
                                ></a
                            >
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</body>
</html>
