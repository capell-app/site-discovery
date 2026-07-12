<x-capell::layout class="public-sitemap-page page-sitemap">
    <div class="vsitemap mb-20">
        <ul>
            @if ($this->results)
                @foreach ($this->results as $sitemapPage)
                    @include ('capell::sitemap.sitemap-page', ['sitemapPage' => $sitemapPage, 'level' => 1])
                @endforeach
            @endif
        </ul>
    </div>

    @once
        <style>
            /***** Vertical Sitemap from https://github.com/kanyarut/VisualSitemap/blob/master/sitemap.css *****/
            .vsitemap {
                --color-line: rgb(203 213 225);
                --item-gap: 0.75rem;
                text-align: left;
            }

            .vsitemap * {
                box-sizing: border-box;
            }

            .vsitemap ul {
                display: grid;
                gap: var(--item-gap);
                margin: 0;
                list-style: none;
                padding: 0;
            }

            .vsitemap ul > li {
                display: grid;
                gap: var(--item-gap);
                margin: 0 0 calc(var(--item-gap) / 2);
                position: relative;
            }

            /* Box Item */
            .vsitemap small {
                line-height: 1.5em;
                position: relative;
                font-size: 0.8em;
            }

            .vsitemap a {
                line-height: 1.5em;
                display: block;
                text-decoration: none;
                padding: 0.75rem 1rem;
                border: 1px solid rgb(226 232 240);
                border-radius: 0.5rem;
                background: white;
                color: rgb(15 23 42);
                font-weight: 700;
                transition:
                    border-color 150ms ease,
                    color 150ms ease;
            }

            .vsitemap a:hover,
            .vsitemap a:focus {
                border-color: rgb(15 118 110);
                color: rgb(15 118 110);
            }

            .vsitemap li li {
                padding-left: 1rem;
                border-left: 1px solid var(--color-line);
            }

            /***** End Vertical Sitemap *****/
        </style>
    @endonce
</x-capell::layout>
