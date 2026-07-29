@extends('layouts.app')

@section('title', $workOrder->work_order_number)
@section('breadcrumb', 'أوامر العمل')
@section('page-title', $workOrder->work_order_number)

@section('content')
<div class="work-order-show-page">
    <section class="work-order-summary sw-card">
        <div class="work-order-summary__content">
            <div>
                <span class="work-order-summary__eyebrow">أمر عمل</span>
                <h1>{{ $workOrder->work_order_number }}</h1>
                <div class="work-order-summary__meta">
                    <span>{{ $workOrder->customer->name }}</span>
                    <span>{{ $workOrder->vehicle->plate_number ?: $workOrder->vehicle->vin }}</span>
                    <span>{{ $workOrder->branch->name }}</span>
                </div>
            </div>

            <div class="work-order-summary__status">
                <x-status-badge :status="$workOrder->status" />
                <small>المخزن: {{ $workOrder->warehouse->name }}</small>
            </div>
        </div>

        <div class="work-order-summary__actions sw-actions">
            @if($workOrder->inspection)
                <a class="sw-button sw-button--outline" href="{{ route('vehicle-inspections.show', $workOrder->inspection) }}">
                    فحص الاستلام والصور
                </a>
            @endif

            @if(auth()->user()->hasPermission('work_order_materials.reserve') && in_array($workOrder->status, ['inspection_completed', 'awaiting_materials']))
                <form method="POST" action="{{ route('work-orders.materials.reserve', $workOrder) }}">
                    @csrf
                    <button class="sw-button sw-button--primary">حجز المواد</button>
                </form>
            @endif

            @if($workOrder->status === 'awaiting_quality' && auth()->user()->hasPermission('quality_checks.create'))
                <form method="POST" action="{{ route('quality-checks.start', $workOrder) }}">
                    @csrf
                    <button class="sw-button sw-button--primary">بدء فحص الجودة</button>
                </form>
            @endif

            @if($workOrder->status === 'ready_for_delivery' && auth()->user()->hasPermission('work_orders.deliver'))
                <a class="sw-button sw-button--primary" href="{{ route('deliveries.show', $workOrder) }}">
                    فحص وتسليم السيارة
                </a>
            @endif

            @if($workOrder->status === 'delivered' && auth()->user()->hasPermission('sales_invoices.create'))
                <form method="POST" action="{{ route('work-orders.invoice', $workOrder) }}">
                    @csrf
                    <button class="sw-button sw-button--primary">إنشاء فاتورة من أمر العمل</button>
                </form>
            @endif
        </div>
    </section>

    @if($workOrder->qualityChecks->isNotEmpty() || $workOrder->reworkOrders->isNotEmpty())
        <div class="work-order-related-grid">
            @if($workOrder->qualityChecks->isNotEmpty())
                <section class="sw-card work-order-section">
                    <div class="work-order-section__header">
                        <h2>جولات الجودة</h2>
                    </div>
                    <div class="work-order-link-list">
                        @foreach($workOrder->qualityChecks as $check)
                            <a href="{{ route('quality-checks.show', $check) }}">
                                <strong>{{ $check->quality_check_number }}</strong>
                                <x-status-badge :status="$check->status" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($workOrder->reworkOrders->isNotEmpty())
                <section class="sw-card work-order-section">
                    <div class="work-order-section__header">
                        <h2>إعادة العمل</h2>
                    </div>
                    <div class="work-order-link-list">
                        @foreach($workOrder->reworkOrders as $rework)
                            <a href="{{ route('rework-orders.show', $rework) }}">
                                <strong>{{ $rework->rework_number }}</strong>
                                <x-status-badge :status="$rework->status" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif

    <section class="sw-card work-order-section">
        <div class="work-order-section__header">
            <div>
                <h2>الخدمات والفنيون</h2>
                <p>إسناد الفنيين ومتابعة حالة تنفيذ كل خدمة.</p>
            </div>
        </div>

        <div class="work-order-services">
            @forelse($workOrder->services as $line)
                <article class="work-order-service">
                    <div class="work-order-service__heading">
                        <div>
                            <h3>{{ $line->description }}</h3>
                            <p>الفنيون: {{ $line->technicians->pluck('employee.name')->join('، ') ?: 'غير مسند' }}</p>
                        </div>
                        <x-status-badge :status="$line->status" />
                    </div>

                    @php
                        $lineSkills = $qualifiedTechnicians->get($line->service_id, collect());
                        $inspectionReady = $workOrder->inspection && in_array($workOrder->inspection->status, ['completed', 'customer_acknowledged'], true);
                        $technicianReady = $line->technicians->isNotEmpty();
                        $materialsReady = ! $line->materials->contains(fn ($material) => $material->status === 'planned');
                    @endphp

                    <div class="work-order-readiness">
                        <span class="{{ $inspectionReady ? 'is-ready' : '' }}">الفحص: {{ $inspectionReady ? 'مكتمل' : 'غير مكتمل' }}</span>
                        <span class="{{ $technicianReady ? 'is-ready' : '' }}">الفني: {{ $technicianReady ? 'مسند' : 'غير مسند' }}</span>
                        <span class="{{ $materialsReady ? 'is-ready' : '' }}">المواد: {{ $materialsReady ? 'جاهزة' : 'غير جاهزة' }}</span>
                    </div>

                    @if($line->technicians->isNotEmpty())
                        <div class="work-order-technician-list">
                            @foreach($line->technicians as $technician)
                                @php($assignedSkill = $technician->employee?->serviceSkills->firstWhere('service_id', $line->service_id))
                                <div class="work-order-technician-card">
                                    <strong>{{ $technician->employee?->employee_code }} — {{ $technician->employee?->name }}</strong>
                                    <span>{{ ['lead' => 'فني رئيسي', 'technician' => 'فني', 'assistant' => 'مساعد فني', 'reviewer' => 'مراجع'][$technician->role] ?? $technician->role }}</span>
                                    @if($technician->is_primary)<span>أساسي</span>@endif
                                    <span>{{ ['trainee' => 'متدرب', 'junior' => 'مبتدئ', 'intermediate' => 'متوسط', 'senior' => 'متقدم', 'expert' => 'خبير'][$assignedSkill?->skill_level] ?? '—' }}</span>
                                    <span>{{ $technician->assigned_at?->format('Y-m-d H:i') ?: '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(auth()->user()->hasPermission('work_orders.assign_technicians'))
                        @if($lineSkills->isEmpty())
                            <div class="work-order-no-technician">
                                <p>لا يوجد فني مؤهل ونشط لهذه الخدمة في فرع أمر العمل.</p>
                                @if(auth()->user()->hasPermission('employees.create'))
                                    <a class="sw-button sw-button--outline" href="{{ route('employees.create', [
                                        'branch_id' => $workOrder->branch_id,
                                        'service_id' => $line->service_id,
                                        'return_url' => route('work-orders.show', $workOrder),
                                    ]) }}">إضافة فني مؤهل</a>
                                @endif
                            </div>
                        @else
                            <form class="work-order-technician-form" method="POST" action="{{ route('work-order-services.technicians.store', $line) }}">
                                @csrf
                                <label>
                                    <span>الفني المؤهل</span>
                                    <select name="employee_id" required>
                                        @foreach($lineSkills as $skill)
                                            <option value="{{ $skill->employee_id }}">
                                                {{ $skill->employee->employee_code }} — {{ $skill->employee->name }} — {{ $skill->employee->job_title }} — {{ ['trainee' => 'متدرب', 'junior' => 'مبتدئ', 'intermediate' => 'متوسط', 'senior' => 'متقدم', 'expert' => 'خبير'][$skill->skill_level] ?? $skill->skill_level }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span>الدور</span>
                                    <select name="role">
                                        <option value="technician">فني</option>
                                        <option value="lead">فني رئيسي</option>
                                        <option value="assistant">مساعد فني</option>
                                        <option value="reviewer">مراجع</option>
                                    </select>
                                </label>
                                @can('viewCost', $workOrder)
                                    <label>
                                        <span>تكلفة الساعة</span>
                                        <input type="number" step="0.0001" min="0" name="hourly_cost_snapshot" placeholder="0.00">
                                    </label>
                                @endcan
                                <button class="sw-button sw-button--secondary">تعيين الفني</button>
                            </form>
                        @endif
                    @endif

                    <div class="work-order-service__actions sw-actions">
                        @foreach(['start' => 'بدء', 'pause' => 'إيقاف مؤقت', 'resume' => 'استئناف', 'complete' => 'إكمال'] as $action => $label)
                            @if(auth()->user()->hasPermission($action === 'complete' ? 'work_orders.complete' : ($action === 'pause' ? 'work_orders.pause' : 'work_orders.start')))
                                <form method="POST" action="{{ route('work-order-services.action', [$line, $action]) }}">
                                    @csrf
                                    @if($line->technicians->isNotEmpty())
                                        <input type="hidden" name="employee_id" value="{{ $line->technicians->sortByDesc('is_primary')->first()->employee_id }}">
                                    @endif
                                    <button class="sw-button {{ $action === 'complete' ? 'sw-button--primary' : 'sw-button--outline' }}">
                                        {{ $label }}
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </article>
            @empty
                <p class="work-order-empty">لا توجد خدمات مضافة إلى أمر العمل.</p>
            @endforelse
        </div>
    </section>

    <section class="sw-card work-order-section">
        <div class="work-order-section__header">
            <div>
                <h2>المواد</h2>
                <p>الكميات المتوقعة والمصروفة والاستخدام الفعلي.</p>
            </div>
        </div>

        <div class="sw-table-scroll">
            <table class="sw-table work-order-materials-table">
                <thead>
                    <tr>
                        <th>المادة</th>
                        <th>المتوقع</th>
                        <th>المصروف</th>
                        <th>المستخدم</th>
                        <th>المرتجع</th>
                        <th>الهدر</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workOrder->materials as $material)
                        <tr>
                            <td>{{ $material->product?->name }}</td>
                            <td>{{ $material->expected_quantity }}</td>
                            <td>{{ $material->issued_quantity }}</td>
                            <td>{{ $material->used_quantity }}</td>
                            <td>{{ $material->returned_quantity }}</td>
                            <td>{{ $material->waste_quantity }}</td>
                            <td><x-status-badge :status="$material->status" /></td>
                            <td>
                                @if(auth()->user()->hasPermission('work_order_materials.issue') && $material->material_type === 'quantity')
                                    <div class="work-order-material-actions">
                                        <form method="POST" action="{{ route('work-order-materials.issue', $material) }}">
                                            @csrf
                                            <input type="number" step="0.000001" name="quantity" value="{{ $material->expected_quantity }}">
                                            <button class="sw-button sw-button--secondary">صرف</button>
                                        </form>
                                        <form method="POST" action="{{ route('work-order-materials.use', $material) }}">
                                            @csrf
                                            <input type="number" step="0.000001" name="quantity" placeholder="المستخدم">
                                            <input type="number" step="0.000001" name="waste_quantity" placeholder="الهدر">
                                            <button class="sw-button sw-button--outline">تسجيل الاستخدام</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="work-order-empty">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="work-order-empty">لا توجد مواد مضافة إلى أمر العمل.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @can('viewCost', $workOrder)
        <section class="sw-card work-order-section">
            <div class="work-order-section__header">
                <h2>التكلفة الفعلية</h2>
            </div>
            <div class="work-order-cost-grid">
                <div><span>المواد</span><strong>{{ $workOrder->actual_material_cost }}</strong></div>
                <div><span>الهدر</span><strong>{{ $workOrder->actual_waste_cost }}</strong></div>
                <div><span>العمالة</span><strong>{{ $workOrder->actual_labor_cost }}</strong></div>
                <div><span>الإجمالي</span><strong>{{ $workOrder->actual_total_cost }}</strong></div>
                <div><span>الهامش</span><strong>{{ $workOrder->actual_margin }}</strong></div>
            </div>
        </section>
    @endcan

    <section class="sw-card work-order-section">
        <div class="work-order-section__header">
            <h2>الخط الزمني</h2>
        </div>
        <div class="work-order-timeline">
            @forelse($workOrder->statusLogs as $log)
                <div class="work-order-timeline__item">
                    <span>{{ $log->created_at }}</span>
                    <strong>{{ $log->from_status ?: 'created' }} ← {{ $log->to_status }}</strong>
                    @if($log->reason)<p>{{ $log->reason }}</p>@endif
                </div>
            @empty
                <p class="work-order-empty">لا توجد حركات مسجلة حتى الآن.</p>
            @endforelse
        </div>
    </section>

    @can('cancel', $workOrder)
        <form class="sw-card work-order-cancel-form" method="POST" action="{{ route('work-orders.cancel', $workOrder) }}">
            @csrf
            <label>
                <span>سبب إلغاء أمر العمل</span>
                <input name="reason" required placeholder="اكتب سبب الإلغاء">
            </label>
            <button class="sw-button sw-button--danger">إلغاء أمر العمل</button>
        </form>
    @endcan
</div>
@endsection
