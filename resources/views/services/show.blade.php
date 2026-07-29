@extends('layouts.app')
@section('title', $service->name)
@section('page-title', $service->name)
@section('breadcrumb', 'الخدمات / عرض الخدمة')
@section('page-actions')
@if(auth()->user()->hasPermission('services.update'))<a class="sw-button" href="{{ route('services.edit', $service) }}">تعديل البيانات الأساسية</a>@endif
@endsection
@section('content')
<x-catalog-navigation active="services" />
<x-card title="البيانات الأساسية">
<div class="sw-form-grid"><div><small>الكود</small><strong>{{ $service->code }}</strong></div><div><small>التصنيف</small><strong>{{ $service->category?->name }}</strong></div><div><small>التسعير</small><strong>{{ $service->pricing_type }}</strong></div><div><small>المدة</small><strong>{{ $service->default_duration_minutes }} دقيقة</strong></div></div>
<p>{{ $service->description }}</p>
</x-card>

<x-card title="حاسبة تقديرية — لا تُحفظ كمستند"><form method="POST" action="{{ route('services.estimate', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="branch_id" label="الفرع">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.select name="vehicle_size_id" label="حجم السيارة"><option value="">عام</option>@foreach($vehicleSizes as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-form.select>
<x-form.select name="vehicle_type_id" label="نوع السيارة"><option value="">عام</option>@foreach($vehicleTypes as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-form.select>
<x-form.input type="number" step="0.0001" min="0.0001" name="quantity" label="الكمية" value="1" />
</div><div class="sw-form-actions"><x-button type="submit">احسب من الخادم</x-button></div></form></x-card>

@if(auth()->user()->hasPermission('services.manage_branch_availability'))
<x-card title="توافر الفروع"><form method="POST" action="{{ route('services.availability.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="branch_id" label="الفرع">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.input type="number" name="default_duration_minutes" label="مدة الفرع" min="1" />
<x-form.input type="number" step="0.0001" name="default_price" label="السعر الافتراضي (غير شامل الضريبة)" min="0" />
<x-form.input type="number" step="0.0001" name="minimum_price" label="الحد الأدنى" min="0" />
<x-form.input type="number" step="0.0001" name="maximum_discount_percentage" label="أقصى خصم %" min="0" max="100" />
</div><input type="hidden" name="is_available" value="0"><x-form.checkbox name="is_available" label="متاحة" checked /><input type="hidden" name="is_active" value="1"><div class="sw-form-actions"><x-button type="submit">حفظ التوافر</x-button></div></form>
<x-table-shell><thead><tr><th>الفرع</th><th>متاحة</th><th>السعر الافتراضي</th><th>الحد الأدنى</th></tr></thead><tbody>@foreach($service->branchServices as $item)<tr><td>{{ $item->branch?->name }}</td><td>{{ $item->is_available ? 'نعم' : 'لا' }}</td><td>{{ $item->default_price ?? '—' }}</td><td>{{ $canViewCost ? ($item->minimum_price ?? '—') : 'محجوب' }}</td></tr>@endforeach</tbody></x-table-shell>
</x-card>@endif

@if(auth()->user()->hasPermission('services.manage_prices'))
<x-card title="الأسعار حسب الفرع والحجم والنوع والفترة"><form method="POST" action="{{ route('services.prices.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="branch_id" label="الفرع">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.select name="vehicle_size_id" label="الحجم"><option value="">عام</option>@foreach($vehicleSizes as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-form.select>
<x-form.select name="vehicle_type_id" label="النوع"><option value="">عام</option>@foreach($vehicleTypes as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-form.select>
<x-form.select name="unit_id" label="الوحدة"><option value="">بدون</option>@foreach($units as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-form.select>
<x-form.input type="number" step="0.0001" name="price" label="السعر غير شامل الضريبة" min="0" required />
<x-form.input type="number" step="0.0001" name="minimum_price" label="الحد الأدنى" min="0" />
<x-form.input type="date" name="effective_from" label="ساري من" :value="now()->toDateString()" required />
<x-form.input type="date" name="effective_to" label="ساري حتى" />
<x-form.input type="number" name="priority" label="الأولوية" value="0" />
</div><input type="hidden" name="is_active" value="1"><div class="sw-form-actions"><x-button type="submit">إضافة سعر</x-button></div></form>
</x-card>@endif

@if(auth()->user()->hasPermission('services.manage_materials'))
<x-card title="المواد المتوقعة — بدون حجز أو خصم مخزون"><form method="POST" action="{{ route('services.materials.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="product_id" label="المنتج">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</x-form.select>
<x-form.select name="unit_id" label="الوحدة">@foreach($units as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-form.select>
<x-form.select name="requirement_type" label="النوع">@foreach(['primary_film','secondary_film','installation_material','consumable','accessory','tool','other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</x-form.select>
<x-form.input type="number" step="0.000001" name="expected_quantity" label="الكمية المتوقعة" min="0.000001" required />
<x-form.input type="number" step="0.0001" name="expected_waste_percentage" label="الهالك %" value="0" min="0" max="100" />
</div><input type="hidden" name="is_required" value="1"><input type="hidden" name="allow_substitution" value="0"><div class="sw-form-actions"><x-button type="submit">إضافة مادة متوقعة</x-button></div></form>
@if($service->materialRequirements->isNotEmpty())<h3>بدائل المواد</h3><form method="POST" action="{{ route('services.material-substitutes.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="service_material_requirement_id" label="المادة الأصلية">@foreach($service->materialRequirements as $requirement)<option value="{{ $requirement->id }}">{{ $requirement->product?->name }}</option>@endforeach</x-form.select>
<x-form.select name="substitute_product_id" label="المنتج البديل">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</x-form.select>
<x-form.input type="number" step="0.000001" name="conversion_factor" label="معامل التحويل" value="1" min="0.000001" />
<x-form.input type="number" name="priority" label="الأولوية" value="0" min="0" />
</div><div class="sw-form-actions"><x-button type="submit">حفظ البديل</x-button></div></form>@endif
</x-card>@endif

@if(auth()->user()->hasPermission('services.manage_roll_profiles'))
<x-card title="استهلاك الرولات المتوقع"><form method="POST" action="{{ route('services.roll-profiles.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="film_product_id" label="منتج Roll Film"><option value="">Profile عام</option>@foreach($products->where('tracking_type','roll') as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</x-form.select>
<x-form.select name="coverage_type" label="التغطية">@foreach(['full_vehicle','front_package','hood','bumper','fenders','doors','roof','trunk','headlights','windshield','side_windows','rear_window','interior_screen','custom'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</x-form.select>
<x-form.input type="number" step="0.000001" name="expected_area" label="المساحة المتوقعة" min="0.000001" required />
<x-form.input type="number" step="0.0001" name="expected_waste_percentage" label="الهالك %" value="0" min="0" max="100" />
</div><div class="sw-form-actions"><x-button type="submit">إضافة Profile</x-button></div></form></x-card>@endif

@if(auth()->user()->hasPermission('services.manage_skills'))
<x-card title="الفنيون والمهارات"><form method="POST" action="{{ route('services.skills.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="employee_id" label="الموظف">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</x-form.select>
<x-form.select name="skill_level" label="المستوى">@foreach(['trainee','junior','intermediate','senior','expert'] as $level)<option value="{{ $level }}">{{ $level }}</option>@endforeach</x-form.select>
<x-form.input type="date" name="certified_at" label="تاريخ الاعتماد" /><x-form.input type="date" name="certification_expires_at" label="انتهاء الاعتماد" />
</div><input type="hidden" name="is_active" value="1"><div class="sw-form-actions"><x-button type="submit">حفظ المهارة</x-button></div></form></x-card>@endif

@if(auth()->user()->hasPermission('services.manage_commissions'))
<x-card title="قواعد العمولات — Foundation فقط"><form method="POST" action="{{ route('services.commissions.store', $service) }}" class="sw-form">@csrf<div class="sw-form-grid">
<x-form.select name="branch_id" label="الفرع"><option value="">كل الفروع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.select name="employee_id" label="الفني"><option value="">عام</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</x-form.select>
<x-form.select name="role_id" label="الدور"><option value="">عام</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->display_name }}</option>@endforeach</x-form.select>
<x-form.select name="commission_type" label="النوع">@foreach(['fixed','percentage','per_vehicle','per_unit'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</x-form.select>
<x-form.input type="number" step="0.0001" name="commission_value" label="القيمة" min="0" required />
<x-form.select name="calculation_base" label="أساس الحساب">@foreach(['service_price','net_service_price','gross_profit','fixed'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</x-form.select>
<x-form.input type="date" name="effective_from" label="ساري من" :value="now()->toDateString()" required /><x-form.input type="date" name="effective_to" label="ساري حتى" />
</div><input type="hidden" name="is_active" value="1"><div class="sw-form-actions"><x-button type="submit">حفظ القاعدة</x-button></div></form></x-card>@endif
@endsection
