{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ([route('website.home'), route('website.about'), route('website.services'), route('website.contact'), route('website.register')] as $url)
        @foreach (['ar', 'en'] as $locale)
            <url>
                <loc>{{ $url }}?lang={{ $locale }}</loc>
                <changefreq>monthly</changefreq>
                <priority>{{ $loop->parent->first ? '1.0' : '0.8' }}</priority>
            </url>
        @endforeach
    @endforeach
</urlset>
