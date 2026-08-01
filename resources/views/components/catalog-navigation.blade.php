@props(['active' => 'products', 'permissionNames' => null])

@php
    $user = auth()->user();
    $permissionNames ??= $user->roles()
        ->where('roles.is_active', true)
        ->with('permissions:id,name')
        ->get()
        ->flatMap->permissions
        ->pluck('name')
        ->unique();
    $can = fn (string $permission) => $permissionNames->contains($permission);
    $items = [
        ['key' => 'products', 'label' => 'المنتجات', 'route' => 'products.index', 'allowed' => $can('products.view')],
        ['key' => 'services', 'label' => 'الخدمات', 'route' => 'services.index', 'allowed' => $can('services.view')],
        ['key' => 'packages', 'label' => 'باقات الخدمات', 'route' => 'service-packages.index', 'allowed' => $can('service_packages.view')],
        ['key' => 'service-categories', 'label' => 'تصنيفات الخدمات', 'route' => 'service-categories.index', 'allowed' => $can('service_categories.view')],
        ['key' => 'product-categories', 'label' => 'تصنيفات المنتجات', 'route' => 'product-references.index', 'params' => ['categories'], 'allowed' => $can('products.view')],
        ['key' => 'product-brands', 'label' => 'العلامات التجارية', 'route' => 'product-references.index', 'params' => ['brands'], 'allowed' => $can('products.view')],
    ];
@endphp

<nav class="catalog-navigation" aria-label="التنقل بين المنتجات والخدمات">
    @foreach($items as $item)
        @if($item['allowed'])
            <a
                href="{{ route($item['route'], $item['params'] ?? []) }}"
                @class(['catalog-navigation__link', 'is-active' => $active === $item['key']])
                @if($active === $item['key']) aria-current="page" @endif
            >{{ $item['label'] }}</a>
        @endif
    @endforeach
</nav>
